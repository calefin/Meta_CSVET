#!/usr/bin/env python3
"""
Question Parser - Extrai questões de múltiplos formatos (XLSX, DOCX, PDF, JSON)
e estrutura automaticamente usando LLM para identificar enunciado, alternativas e gabarito.
"""

import json
import sys
import re
from pathlib import Path
from typing import List, Dict, Any, Optional

try:
    import openpyxl
except ImportError:
    openpyxl = None

try:
    from docx import Document
except ImportError:
    Document = None

try:
    import pdfplumber
except ImportError:
    pdfplumber = None


class QuestionParser:
    def __init__(self):
        self.questions = []

    def parse_xlsx(self, file_path: str) -> List[Dict[str, Any]]:
        """Parse XLSX file and extract questions."""
        if openpyxl is None:
            return []

        questions = []
        try:
            wb = openpyxl.load_workbook(file_path)
            ws = wb.active

            for row_idx, row in enumerate(ws.iter_rows(values_only=True), 1):
                if row_idx == 1:  # Skip header
                    continue
                if not row or not row[0]:  # Skip empty rows
                    continue

                question_data = {
                    "type": "multiple_choice",
                    "statement": str(row[0]) if row[0] else "",
                    "alternatives": [],
                    "correctAnswer": None,
                    "sourceFormat": "XLSX",
                }

                # Extract alternatives (columns 1-4 typically)
                if len(row) > 1:
                    for i in range(1, min(5, len(row))):
                        if row[i]:
                            question_data["alternatives"].append(str(row[i]))

                # Extract correct answer (last column typically)
                if len(row) > 5 and row[5]:
                    question_data["correctAnswer"] = str(row[5])

                if question_data["statement"]:
                    questions.append(question_data)

            return questions
        except Exception as e:
            print(f"Error parsing XLSX: {e}", file=sys.stderr)
            return []

    def parse_docx(self, file_path: str) -> List[Dict[str, Any]]:
        """Parse DOCX file and extract questions with improved pattern matching."""
        if Document is None:
            return []

        questions = []
        try:
            doc = Document(file_path)
            current_question = None
            question_pattern = r"^\s*\([\d.,\s]+\s*pt\s*\)\s*(.+)"

            for para in doc.paragraphs:
                text = para.text.strip()
                if not text:
                    continue

                # Check if this is a question header (e.g., "(0,5 pt) Question text")
                match = re.match(question_pattern, text)

                if match:
                    # Save previous question if exists
                    if current_question and current_question.get("statement"):
                        questions.append(current_question)

                    # Extract points
                    points_match = re.search(r"\(([\d.,\s]+)\s*pt\)", text)
                    points = 0.5
                    if points_match:
                        try:
                            points_str = points_match.group(1).replace(",", ".").replace(" ", "")
                            points = float(points_str)
                        except ValueError:
                            points = 0.5

                    # Extract question text
                    question_text = match.group(1).strip()

                    current_question = {
                        "type": "essay",  # Default to essay for discursive questions
                        "statement": question_text,
                        "alternatives": [],
                        "correctAnswer": None,
                        "points": points,
                        "sourceFormat": "DOCX",
                    }
                else:
                    # Check if this line contains alternatives (A), (B), (C), etc.
                    alt_pattern = r"^\s*\([A-E]\)\s*(.+)"
                    alt_match = re.match(alt_pattern, text)

                    if alt_match and current_question:
                        # This is a multiple choice question
                        if current_question["type"] == "essay" and len(current_question["alternatives"]) == 0:
                            current_question["type"] = "multiple_choice"

                        alternative = alt_match.group(1).strip()
                        current_question["alternatives"].append(alternative)

            # Save last question
            if current_question and current_question.get("statement"):
                questions.append(current_question)

            return questions
        except Exception as e:
            print(f"Error parsing DOCX: {e}", file=sys.stderr)
            return []

    def parse_pdf(self, file_path: str) -> List[Dict[str, Any]]:
        """Parse PDF file and extract questions with alternatives."""
        if pdfplumber is None:
            return []

        questions = []
        try:
            with pdfplumber.open(file_path) as pdf:
                full_text = ""
                for page in pdf.pages:
                    full_text += page.extract_text() + "\n"

                # Split text into lines
                lines = full_text.split('\n')
                current_question = None
                question_pattern = r"^\s*\([\d.,\s]+\s*pt\s*\)\s*(.+)"

                for line in lines:
                    text = line.strip()
                    if not text:
                        continue

                    # Check if this is a question header
                    match = re.match(question_pattern, text)
                    if match:
                        # Save previous question if exists
                        if current_question and current_question.get("statement"):
                            questions.append(current_question)

                        # Extract points
                        points_match = re.search(r"\(([\d.,\s]+)\s*pt\)", text)
                        points = 0.5
                        if points_match:
                            try:
                                points_str = points_match.group(1).replace(",", ".").replace(" ", "")
                                points = float(points_str)
                            except ValueError:
                                points = 0.5

                        # Extract question text
                        question_text = match.group(1).strip()

                        current_question = {
                            "type": "essay",
                            "statement": question_text,
                            "alternatives": [],
                            "correctAnswer": None,
                            "points": points,
                            "sourceFormat": "PDF",
                        }
                    else:
                        # Check if this line contains alternatives
                        alt_pattern = r"^\s*\([A-E]\)\s*(.+)"
                        alt_match = re.match(alt_pattern, text)

                        if alt_match and current_question:
                            # This is a multiple choice question
                            if current_question["type"] == "essay" and len(current_question["alternatives"]) == 0:
                                current_question["type"] = "multiple_choice"

                            alternative = alt_match.group(1).strip()
                            current_question["alternatives"].append(alternative)

                # Save last question
                if current_question and current_question.get("statement"):
                    questions.append(current_question)

            return questions
        except Exception as e:
            print(f"Error parsing PDF: {e}", file=sys.stderr)
            return []

    def parse_json(self, file_path: str) -> List[Dict[str, Any]]:
        """Parse JSON file and extract questions."""
        try:
            with open(file_path, "r", encoding="utf-8") as f:
                data = json.load(f)

            if isinstance(data, list):
                questions = data
            elif isinstance(data, dict) and "questions" in data:
                questions = data["questions"]
            else:
                questions = [data] if isinstance(data, dict) else []

            # Ensure all questions have required properties
            for question in questions:
                if "type" not in question:
                    question["type"] = "essay"
                if "statement" not in question:
                    question["statement"] = ""
                if "alternatives" not in question:
                    question["alternatives"] = []
                if "correctAnswer" not in question:
                    question["correctAnswer"] = None
                if "sourceFormat" not in question:
                    question["sourceFormat"] = "JSON"

            return questions
        except Exception as e:
            print(f"Error parsing JSON: {e}", file=sys.stderr)
            return []

    def parse_file(self, file_path: str, file_type: str) -> List[Dict[str, Any]]:
        """Parse file based on type."""
        file_type = file_type.lower()

        if file_type == "xlsx":
            return self.parse_xlsx(file_path)
        elif file_type == "docx":
            return self.parse_docx(file_path)
        elif file_type == "pdf":
            return self.parse_pdf(file_path)
        elif file_type == "json":
            return self.parse_json(file_path)
        else:
            raise ValueError(f"Unsupported file type: {file_type}")

    def to_json(self) -> str:
        """Convert parsed questions to JSON."""
        return json.dumps(self.questions, ensure_ascii=False, indent=2)


def main():
    """Main entry point for CLI usage."""
    if len(sys.argv) < 3:
        print("Usage: python question-parser.py <file_path> <file_type>")
        print("Supported types: xlsx, docx, pdf, json")
        sys.exit(1)

    file_path = sys.argv[1]
    file_type = sys.argv[2]

    parser = QuestionParser()
    questions = parser.parse_file(file_path, file_type)

    # Output as JSON to stdout
    print(json.dumps(questions, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
