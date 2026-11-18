#!/usr/bin/env python3
"""
PDF Generator for Academic Exams
Generates A4 PDFs with:
  - Page 1: answer sheet (bubble sheet) embedded from SVG
  - Pages 2+: exam questions in 2-column layout for MC, full-width for essays
"""

import json
import sys
import random
import base64
from io import BytesIO
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import cm
from reportlab.lib.colors import HexColor, black
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, PageBreak,
    Table, TableStyle, Image, KeepTogether,
)
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_JUSTIFY
from reportlab.lib.utils import ImageReader

try:
    import cairosvg
    CAIROSVG_AVAILABLE = True
except ImportError:
    CAIROSVG_AVAILABLE = False

PAGE_W, PAGE_H = A4  # 595.28, 841.89 points
LEFT_MARGIN = 1.5 * cm
RIGHT_MARGIN = 1.5 * cm
TOP_MARGIN = 1.5 * cm
BOTTOM_MARGIN = 1.5 * cm
CONTENT_W = PAGE_W - LEFT_MARGIN - RIGHT_MARGIN


import re
import html

def _esc(text: str) -> str:
    """Escape special XML/HTML characters for ReportLab Paragraph."""
    return (
        str(text)
        .replace("&", "&amp;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
    )

def _format_text_for_reportlab(text: str) -> str:
    """Formats plain/markdown/HTML text safely for ReportLab paragraph blocks."""
    if not text:
        return ""
    # 1. Escape basic XML/HTML special characters first
    escaped = _esc(text)
    
    # 2. Restore allowed HTML formatting tags
    escaped = (
        escaped
        .replace("&lt;b&gt;", "<b>")
        .replace("&lt;/b&gt;", "</b>")
        .replace("&lt;i&gt;", "<i>")
        .replace("&lt;/i&gt;", "</i>")
        .replace("&lt;u&gt;", "<u>")
        .replace("&lt;/u&gt;", "</u>")
        .replace("&lt;br&gt;", "<br/>")
        .replace("&lt;br/&gt;", "<br/>")
    )
    
    # 3. Translate basic Markdown formatting to ReportLab tags
    # Bold: **text** or __text__
    escaped = re.sub(r'\*\*(.*?)\*\*', r'<b>\1</b>', escaped)
    escaped = re.sub(r'__(.*?)__', r'<b>\1</b>', escaped)
    
    # Italic: *text* or _text_
    escaped = re.sub(r'\*(.*?)\*', r'<i>\1</i>', escaped)
    escaped = re.sub(r'_(.*?)_', r'<i>\1</i>', escaped)
    
    return escaped


class ExamPDFGenerator:
    def __init__(self, exam_data, questions, options):
        self.exam_data = exam_data
        self.questions = questions
        self.answer_sheet_svg_b64 = options.get("answerSheetSvg")
        self.exam_id = options.get("examId")
        self.shuffle_mode = options.get("shuffleMode", "all")
        self.pdf_layout = options.get("pdfLayout", "merged")
        self.current_version = 1
        self._answer_sheet_reader = None
        self.styles = getSampleStyleSheet()
        self._setup_styles()
        self._cache_answer_sheet_png()

    def _setup_styles(self):
        self.styles.add(ParagraphStyle(
            name="ExamTitle",
            parent=self.styles["Heading1"],
            fontSize=14,
            textColor=HexColor("#1e293b"),
            spaceAfter=2,
            alignment=TA_CENTER,
            fontName="Helvetica-Bold",
        ))
        self.styles.add(ParagraphStyle(
            name="ExamMeta",
            parent=self.styles["Normal"],
            fontSize=9,
            textColor=HexColor("#475569"),
            spaceAfter=2,
            alignment=TA_CENTER,
            fontName="Helvetica",
        ))
        self.styles.add(ParagraphStyle(
            name="Instructions",
            parent=self.styles["Normal"],
            fontSize=9,
            textColor=HexColor("#334155"),
            spaceAfter=4,
            alignment=TA_JUSTIFY,
            fontName="Helvetica",
            leading=12,
        ))
        self.styles.add(ParagraphStyle(
            name="QStatement",
            parent=self.styles["Normal"],
            fontSize=10,
            textColor=black,
            spaceAfter=3,
            spaceBefore=3,
            fontName="Helvetica-Bold",
            leading=13,
        ))
        self.styles.add(ParagraphStyle(
            name="Alternative",
            parent=self.styles["Normal"],
            fontSize=9.5,
            textColor=HexColor("#1e293b"),
            spaceAfter=2,
            leftIndent=6,
            fontName="Helvetica",
            leading=12,
        ))
        self.styles.add(ParagraphStyle(
            name="EssayMeta",
            parent=self.styles["Normal"],
            fontSize=8,
            textColor=HexColor("#666666"),
            fontName="Helvetica",
        ))
        self.styles.add(ParagraphStyle(
            name="StudentField",
            parent=self.styles["Normal"],
            fontSize=9,
            textColor=HexColor("#1e293b"),
            fontName="Helvetica",
        ))

    def _get_image_flowable(self, img_b64, max_w, max_h=150):
        if not img_b64:
            return None
        try:
            if "," in img_b64:
                img_b64 = img_b64.split(",", 1)[1]
            img_bytes = base64.b64decode(img_b64)
            img_io = BytesIO(img_bytes)
            
            reader = ImageReader(img_io)
            orig_w, orig_h = reader.getSize()
            
            ratio = orig_w / orig_h
            if orig_w > max_w:
                w = max_w
                h = w / ratio
            else:
                w = orig_w
                h = orig_h
                
            if h > max_h:
                h = max_h
                w = h * ratio
                
            img_io.seek(0)
            return Image(img_io, width=w, height=h)
        except Exception as e:
            sys.stderr.write(f"Error loading image in PDF: {str(e)}\n")
            return None

    # ── Answer sheet page ────────────────────────────────────────────────────

    def _cache_answer_sheet_png(self):
        """Convert answer sheet SVG to PNG once; cache as BytesIO for reuse."""
        if not self.answer_sheet_svg_b64 or not CAIROSVG_AVAILABLE:
            self._answer_sheet_reader = None
            return
        try:
            svg_bytes = base64.b64decode(self.answer_sheet_svg_b64)
            png_bytes = cairosvg.svg2png(
                bytestring=svg_bytes,
                output_width=int(PAGE_W * 2),
                output_height=int(PAGE_H * 2),
            )
            self._answer_sheet_reader = ImageReader(BytesIO(png_bytes))
        except Exception:
            self._answer_sheet_reader = None

    # ── Header ───────────────────────────────────────────────────────────────

    def _build_header(self, elements, version):
        elements.append(Paragraph(_esc(self.exam_data.get("name", "Avaliação")), self.styles["ExamTitle"]))

        meta_parts = []
        if self.exam_data.get("discipline"):
            meta_parts.append(f"<b>Disciplina:</b> {_esc(self.exam_data['discipline'])}")
        if self.exam_data.get("course"):
            meta_parts.append(f"<b>Curso:</b> {_esc(self.exam_data['course'])}")
        if self.exam_data.get("professor"):
            meta_parts.append(f"<b>Professor(a):</b> {_esc(self.exam_data['professor'])}")
        if self.exam_data.get("class"):
            meta_parts.append(f"<b>Turma:</b> {_esc(self.exam_data['class'])}")
        if self.exam_data.get("examDate"):
            meta_parts.append(f"<b>Data:</b> {_esc(self.exam_data['examDate'])}")
        if version > 1:
            meta_parts.append(f"<b>Versão:</b> {chr(64 + version)}")

        if meta_parts:
            elements.append(Paragraph("  |  ".join(meta_parts), self.styles["ExamMeta"]))

        if self.exam_data.get("instructions"):
            elements.append(Spacer(1, 0.15 * cm))
            text = _esc(self.exam_data["instructions"]).replace("\n", "<br/>")
            elements.append(Paragraph(f"<b>Instruções:</b> {text}", self.styles["Instructions"]))

        # Student identification row
        elements.append(Spacer(1, 0.2 * cm))
        id_row = Table(
            [[
                Paragraph("Nome: ___________________________________________", self.styles["StudentField"]),
                Paragraph("Turma: _______________", self.styles["StudentField"]),
                Paragraph("Data: ________________", self.styles["StudentField"]),
            ]],
            colWidths=[CONTENT_W * 0.55, CONTENT_W * 0.22, CONTENT_W * 0.23],
        )
        id_row.setStyle(TableStyle([
            ("VALIGN", (0, 0), (-1, -1), "BOTTOM"),
            ("LEFTPADDING", (0, 0), (-1, -1), 0),
            ("RIGHTPADDING", (0, 0), (-1, -1), 4),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
            ("TOPPADDING", (0, 0), (-1, -1), 0),
        ]))
        elements.append(id_row)
        elements.append(Spacer(1, 0.25 * cm))

        # Horizontal rule
        rule = Table([[""]],
                     colWidths=[CONTENT_W],
                     rowHeights=[0.5])
        rule.setStyle(TableStyle([
            ("LINEBELOW", (0, 0), (-1, -1), 0.75, HexColor("#94a3b8")),
            ("TOPPADDING", (0, 0), (-1, -1), 0),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
        ]))
        elements.append(rule)
        elements.append(Spacer(1, 0.3 * cm))

    # ── Shuffle logic ────────────────────────────────────────────────────────

    def _shuffle_questions(self, questions, version):
        mode = self.shuffle_mode
        rng = random.Random(version)

        result = list(questions)

        if mode in ("all", "questions"):
            rng.shuffle(result)

        if mode in ("all", "items"):
            rng2 = random.Random(version + 10000)
            shuffled = []
            for q in result:
                q_copy = dict(q)
                q_type = q_copy.get("type", "essay")
                if q_type in ("multiple_choice", "multiple_choice_complex") and q_copy.get("alternatives"):
                    alts = list(q_copy["alternatives"])
                    
                    correct_letters = []
                    if "correctAnswers" in q_copy and q_copy["correctAnswers"]:
                        correct_letters = [str(l).upper() for l in q_copy["correctAnswers"]]
                    elif "correctAnswer" in q_copy and q_copy["correctAnswer"]:
                        correct_letters = [c.upper() for c in str(q_copy["correctAnswer"])]
                    
                    correct_texts = []
                    for letter in correct_letters:
                        if len(letter) == 1 and letter.isalpha():
                            idx = ord(letter) - 65
                            if 0 <= idx < len(alts):
                                correct_texts.append(alts[idx])
                                
                    if not correct_texts and "correctAnswer" in q_copy and q_copy["correctAnswer"]:
                        val = q_copy["correctAnswer"]
                        if val in alts:
                            correct_texts.append(val)
                            
                    rng2.shuffle(alts)
                    q_copy["alternatives"] = alts
                    
                    new_correct_letters = []
                    for text in correct_texts:
                        try:
                            new_idx = alts.index(text)
                            new_correct_letters.append(chr(65 + new_idx))
                        except ValueError:
                            pass
                            
                    if not new_correct_letters:
                        new_correct_letters = ["A"]
                        
                    new_correct_letters.sort()
                    q_copy["correctAnswers"] = new_correct_letters
                    q_copy["correctAnswer"] = "".join(new_correct_letters)
                    
                shuffled.append(q_copy)
            result = shuffled

        return result

    # ── Question renderers ───────────────────────────────────────────────────

    def _question_label(self, idx, question):
        pts = question.get("points")
        if pts is not None:
            try:
                pts_str = f" ({float(pts):.2f})"
            except (ValueError, TypeError):
                pts_str = ""
        else:
            pts_str = ""
        return f"<b>Q.{idx}{_esc(pts_str)}</b>"

    def _mc_paragraphs(self, idx, question):
        label = self._question_label(idx, question)
        stmt = _format_text_for_reportlab(question.get("statement", ""))
        paras = [Paragraph(f"{label}&nbsp;&nbsp;{stmt}", self.styles["QStatement"])]

        # Render base64/stored images if present
        col_w = (CONTENT_W - 6) / 2
        for img_b64 in question.get("images", []):
            img_flow = self._get_image_flowable(img_b64, col_w - 10)
            if img_flow:
                paras.append(img_flow)
                paras.append(Spacer(1, 0.1 * cm))

        for alt_idx, alt in enumerate(question.get("alternatives", [])):
            letter = chr(65 + alt_idx)
            paras.append(Paragraph(f"( )&nbsp;<b>{letter}</b>&nbsp;&nbsp;{_format_text_for_reportlab(alt)}", self.styles["Alternative"]))

        paras.append(Spacer(1, 0.25 * cm))
        return paras

    def _essay_block(self, idx, question):
        label = self._question_label(idx, question)
        stmt = _format_text_for_reportlab(question.get("statement", ""))
        pts = question.get("points")

        paras = [
            Paragraph(f"{label}&nbsp;&nbsp;{stmt}", self.styles["QStatement"]),
            Spacer(1, 0.1 * cm),
        ]

        # Render assertion_reason items if applicable
        q_type = question.get("type", "essay")
        if q_type == "assertion_reason":
            q_content = question.get("content", {})
            if q_content and isinstance(q_content, dict):
                items = q_content.get("items", [])
                if items:
                    paras.append(Spacer(1, 0.1 * cm))
                    for item in items:
                        item_id = item.get("id", 1)
                        romans = {1: "I", 2: "II", 3: "III", 4: "IV", 5: "V"}
                        roman = romans.get(item_id, str(item_id))
                        
                        assertion = _format_text_for_reportlab(item.get("assertion", ""))
                        reason = _format_text_for_reportlab(item.get("reason", ""))
                        
                        item_text = f"<b>{roman}.</b> {assertion} <b>PORQUE</b> {reason}."
                        paras.append(Paragraph(item_text, self.styles["Alternative"]))
                    paras.append(Spacer(1, 0.15 * cm))

        # Render images
        for img_b64 in question.get("images", []):
            img_flow = self._get_image_flowable(img_b64, CONTENT_W - 20, max_h=200)
            if img_flow:
                paras.append(img_flow)
                paras.append(Spacer(1, 0.15 * cm))

        # Get linesCount from content (default to 5 for essay, 3 for assertion_reason)
        lines_count = 5
        q_content = question.get("content", {})
        if isinstance(q_content, dict):
            lines_count = q_content.get("linesCount", 5 if q_type == "essay" else 3)

        line_style = ParagraphStyle(
            "AnswerLine",
            parent=self.styles["Normal"],
            fontSize=10,
            textColor=HexColor("#cccccc"),
            leading=18,
        )
        for _ in range(lines_count):
            paras.append(Paragraph("_" * 85, line_style))

        # Scoring box
        if pts is not None:
            try:
                pts_val = f"{float(pts):.2f}"
            except (ValueError, TypeError):
                pts_val = str(pts)

            warning = Paragraph(
                '<font size="7.5" color="#888888"><b>Preencher somente pelo avaliador</b></font>',
                self.styles["EssayMeta"],
            )
            score_field = Paragraph(
                f'<font size="9"><b>Nota: _______ / {pts_val}</b></font>',
                self.styles["EssayMeta"],
            )
            box = Table(
                [[warning, score_field]],
                colWidths=[CONTENT_W - 5.5 * cm, 5.5 * cm],
            )
            box.setStyle(TableStyle([
                ("BOX", (0, 0), (-1, -1), 0.6, HexColor("#bbbbbb")),
                ("BACKGROUND", (0, 0), (-1, -1), HexColor("#f9f9f9")),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("TOPPADDING", (0, 0), (-1, -1), 4),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
                ("LEFTPADDING", (0, 0), (-1, -1), 6),
                ("RIGHTPADDING", (0, 0), (-1, -1), 6),
                ("LINEAFTER", (0, 0), (0, -1), 0.6, HexColor("#bbbbbb")),
            ]))
            paras.append(Spacer(1, 0.1 * cm))
            paras.append(box)

        paras.append(Spacer(1, 0.4 * cm))
        return paras

    # ── 2-column MC layout ────────────────────────────────────────────────────

    def _make_2col(self, left_paras, right_paras):
        col_w = (CONTENT_W - 6) / 2

        def cell(paras):
            if not paras:
                return Paragraph("", self.styles["Normal"])
            # Wrap in a nested Table to allow multiple paragraphs in one cell
            rows = [[p] for p in paras]
            t = Table(rows, colWidths=[col_w])
            t.setStyle(TableStyle([
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("LEFTPADDING", (0, 0), (-1, -1), 0),
                ("RIGHTPADDING", (0, 0), (-1, -1), 0),
                ("TOPPADDING", (0, 0), (-1, -1), 0),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
            ]))
            return t

        outer = Table(
            [[cell(left_paras), cell(right_paras)]],
            colWidths=[col_w, col_w + 6],
        )
        outer.setStyle(TableStyle([
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("LEFTPADDING", (0, 0), (-1, -1), 0),
            ("RIGHTPADDING", (1, 0), (1, -1), 0),
            ("LEFTPADDING", (1, 0), (1, -1), 6),
            ("TOPPADDING", (0, 0), (-1, -1), 0),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
        ]))
        return outer

    def _build_questions(self, elements, questions, version):
        shuffled = self._shuffle_questions(questions, version)
        mc_buffer = []  # [(idx, question)]

        def flush_mc(mc_list):
            pairs = [(mc_list[i], mc_list[i + 1] if i + 1 < len(mc_list) else None)
                     for i in range(0, len(mc_list), 2)]
            for left, right in pairs:
                left_p = self._mc_paragraphs(left[0], left[1])
                right_p = self._mc_paragraphs(right[0], right[1]) if right else []
                elements.append(self._make_2col(left_p, right_p))

        for idx, question in enumerate(shuffled, 1):
            q_type = question.get("type", "essay")
            is_mc = q_type in ("multiple_choice", "multiple_choice_complex")

            if is_mc:
                mc_buffer.append((idx, question))
            else:
                if mc_buffer:
                    flush_mc(mc_buffer)
                    mc_buffer = []
                block = self._essay_block(idx, question)
                elements.append(KeepTogether(block))

        if mc_buffer:
            flush_mc(mc_buffer)

    # ── Canvas callbacks ─────────────────────────────────────────────────────

    def _on_first_page(self, canvas_obj, doc):
        """Page 1: draw answer sheet full-bleed if available, no footer."""
        include_answer_sheet = (
            self.answer_sheet_svg_b64 is not None and 
            self.pdf_layout in ("merged", "answer_sheet_only")
        )
        if include_answer_sheet and self._answer_sheet_reader:
            canvas_obj.drawImage(
                self._answer_sheet_reader, 0, 0, PAGE_W, PAGE_H,
            )

    def _on_later_pages(self, canvas_obj, doc):
        """Pages 2+: draw footer."""
        canvas_obj.saveState()
        canvas_obj.setFont("Helvetica", 7.5)
        canvas_obj.setFillColor(HexColor("#94a3b8"))

        page = canvas_obj.getPageNumber()
        # When answer sheet is on page 1, question pages start at page 2
        question_page = (page - 1) if self._answer_sheet_reader else page
        version_label = chr(64 + self.current_version) if self.current_version > 1 else str(self.current_version)

        if self.exam_id:
            canvas_obj.drawString(
                LEFT_MARGIN, 0.45 * cm,
                f"prof-aid.com/verificar/{self.exam_id}",
            )
        canvas_obj.drawRightString(
            PAGE_W - RIGHT_MARGIN, 0.45 * cm,
            f"Pág. {question_page}  |  Versão {version_label}",
        )
        canvas_obj.restoreState()

    # ── PDF generation ────────────────────────────────────────────────────────

    def generate_pdf(self, version=1):
        self.current_version = version
        buffer = BytesIO()

        doc = SimpleDocTemplate(
            buffer,
            pagesize=A4,
            topMargin=TOP_MARGIN,
            bottomMargin=BOTTOM_MARGIN,
            leftMargin=LEFT_MARGIN,
            rightMargin=RIGHT_MARGIN,
        )

        elements = []

        # Determine whether to include answer sheet based on layout
        include_answer_sheet = (
            self.answer_sheet_svg_b64 is not None and 
            self.pdf_layout in ("merged", "answer_sheet_only")
        )

        if include_answer_sheet:
            elements.append(PageBreak())

        include_questions = self.pdf_layout in ("merged", "questions_only")

        if self.pdf_layout == "answer_sheet_only":
            self._build_header(elements, version)
        elif include_questions:
            self._build_header(elements, version)
            self._build_questions(elements, self.questions, version)

        doc.build(
            elements,
            onFirstPage=self._on_first_page,
            onLaterPages=self._on_later_pages,
        )
        buffer.seek(0)
        return buffer

    def generate_multiple_pdfs(self, num_versions):
        return [self.generate_pdf(v) for v in range(1, num_versions + 1)]


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Missing input data"}))
        sys.exit(1)

    try:
        input_data = json.loads(sys.argv[1])

        exam_data = input_data.get("exam", {})
        questions = input_data.get("questions", [])
        num_versions = input_data.get("numberOfVersions", 1)

        options = {
            "answerSheetSvg": input_data.get("answerSheetSvg"),
            "examId": input_data.get("examId"),
            "shuffleMode": input_data.get("shuffleMode", "all"),
            "fontSize": input_data.get("fontSize", "normal"),
            "margins": input_data.get("margins", "normal"),
            "pdfLayout": input_data.get("pdfLayout", "merged"),
        }

        generator = ExamPDFGenerator(exam_data, questions, options)
        pdfs = generator.generate_multiple_pdfs(num_versions)

        result = {"success": True, "pdfs": []}
        for idx, pdf in enumerate(pdfs, 1):
            result["pdfs"].append({
                "version": idx,
                "data": base64.b64encode(pdf.getvalue()).decode("utf-8"),
                "filename": f"prova_v{idx}.pdf",
            })

        print(json.dumps(result))

    except Exception as e:
        import traceback
        print(json.dumps({"error": str(e), "trace": traceback.format_exc()}))
        sys.exit(1)


if __name__ == "__main__":
    main()
