import os
import json
from pathlib import Path

# Try to import required libraries
try:
    import openpyxl
    from openpyxl.styles import Font, Alignment, PatternFill
except ImportError:
    openpyxl = None

try:
    import docx
    from docx.shared import Inches, Pt
except ImportError:
    docx = None

def generate_xlsx_questions(output_path):
    if openpyxl is None:
        print("openpyxl is not installed, skipping xlsx questions template generation.")
        return False
    
    wb = openpyxl.Workbook()
    ws = wb.active
    ws.title = "Questões"
    
    # Headers
    headers = [
        "Enunciado (Pergunta)", 
        "Alternativa A", 
        "Alternativa B", 
        "Alternativa C", 
        "Alternativa D", 
        "Alternativa E", 
        "Gabarito (A-E)"
    ]
    
    ws.append(headers)
    
    # Font and styling
    header_font = Font(name="Arial", size=11, bold=True, color="FFFFFF")
    header_fill = PatternFill(start_color="1B365D", end_color="1B365D", fill_type="solid")
    center_align = Alignment(horizontal="center", vertical="center")
    
    for col_idx in range(1, len(headers) + 1):
        cell = ws.cell(row=1, column=col_idx)
        cell.font = header_font
        cell.fill = header_fill
        cell.alignment = center_align
        
    # Sample questions
    data = [
        [
            "Qual é a fórmula química da água?", 
            "CO2 (Dióxido de Carbono)", 
            "H2O (Água)", 
            "O2 (Oxigênio)", 
            "NaCl (Sal de Cozinha)", 
            "H2 (Hidrogênio)", 
            "B"
        ],
        [
            "Quem é o autor de 'Dom Casmurro'?", 
            "Clarice Lispector", 
            "Jorge Amado", 
            "Machado de Assis", 
            "Monteiro Lobato", 
            "José de Alencar", 
            "C"
        ],
        [
            "Qual o planeta mais próximo do Sol?", 
            "Vênus", 
            "Mercúrio", 
            "Terra", 
            "Marte", 
            "Júpiter", 
            "B"
        ]
    ]
    
    for row in data:
        ws.append(row)
        
    # Adjust column widths
    for col in ws.columns:
        max_len = max(len(str(cell.value or '')) for cell in col)
        col_letter = openpyxl.utils.get_column_letter(col[0].column)
        ws.column_dimensions[col_letter].width = max(max_len + 3, 12)
        
    wb.save(output_path)
    print(f"XLSX questions template generated at {output_path}")
    return True

def generate_docx_questions(output_path):
    if docx is None:
        print("python-docx is not installed, skipping docx questions template generation.")
        return False
    
    doc = docx.Document()
    
    # Header
    title = doc.add_paragraph()
    run = title.add_run("Modelo de Importação de Questões — ProvaGen")
    run.font.name = "Arial"
    run.font.size = Pt(16)
    run.bold = True
    
    doc.add_paragraph("Instruções: Para importar corretamente, inicie cada questão com a pontuação entre parênteses, ex: '(1,0 pt) Enunciado'. Alternativas devem começar com '(A) ', '(B) ', etc.")
    doc.add_paragraph().add_run("-" * 40).bold = True
    
    # Question 1 (Multiple Choice)
    q1 = doc.add_paragraph()
    q1.add_run("(1,0 pt) Qual é a capital da França?").bold = True
    
    doc.add_paragraph("(A) Londres")
    doc.add_paragraph("(B) Berlim")
    doc.add_paragraph("(C) Paris")
    doc.add_paragraph("(D) Madrid")
    doc.add_paragraph("(E) Roma")
    
    doc.add_paragraph()
    
    # Question 2 (Essay)
    q2 = doc.add_paragraph()
    q2.add_run("(2,5 pt) Descreva o processo de fotossíntese e sua importância para o ecossistema terrestre.").bold = True
    
    doc.add_paragraph()
    
    # Question 3 (Multiple Choice)
    q3 = doc.add_paragraph()
    q3.add_run("(1,5 pt) Quantos continentes existem no planeta Terra?").bold = True
    doc.add_paragraph("(A) 4")
    doc.add_paragraph("(B) 5")
    doc.add_paragraph("(C) 6")
    doc.add_paragraph("(D) 7")
    doc.add_paragraph("(E) 8")
    
    doc.save(output_path)
    print(f"DOCX questions template generated at {output_path}")
    return True

def generate_json_questions(output_path):
    questions = [
        {
            "type": "multiple_choice",
            "statement": "Qual é a velocidade da luz no vácuo aproximadamente?",
            "alternatives": [
                "150.000 km/s",
                "300.000 km/s",
                "450.000 km/s",
                "600.000 km/s"
            ],
            "correctAnswer": "B"
        },
        {
            "type": "essay",
            "statement": "Explique o impacto da Revolução Industrial na urbanização das cidades europeias.",
            "alternatives": [],
            "correctAnswer": None
        }
    ]
    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(questions, f, ensure_ascii=False, indent=2)
    print(f"JSON questions template generated at {output_path}")

def generate_xlsx_students(output_path):
    if openpyxl is None:
        return False
    
    wb = openpyxl.Workbook()
    ws = wb.active
    ws.title = "Alunos"
    
    headers = ["studentId", "externalRef", "firstName", "lastName", "email"]
    ws.append(headers)
    
    # Style
    header_font = Font(name="Arial", size=11, bold=True, color="FFFFFF")
    header_fill = PatternFill(start_color="10B981", end_color="10B981", fill_type="solid")
    
    for col_idx in range(1, len(headers) + 1):
        cell = ws.cell(row=1, column=col_idx)
        cell.font = header_font
        cell.fill = header_fill
        
    data = [
        ["10001", "RA-88392", "Ana", "Souza", "ana.souza@universidade.edu.br"],
        ["10002", "RA-11293", "Carlos", "Eduardo", "carlos.eduardo@universidade.edu.br"],
        ["10003", "RA-49923", "Beatriz", "Melo", "beatriz.melo@universidade.edu.br"]
    ]
    
    for row in data:
        ws.append(row)
        
    # Adjust columns
    for col in ws.columns:
        max_len = max(len(str(cell.value or '')) for cell in col)
        col_letter = openpyxl.utils.get_column_letter(col[0].column)
        ws.column_dimensions[col_letter].width = max(max_len + 3, 12)
        
    wb.save(output_path)
    print(f"XLSX students template generated at {output_path}")
    return True

def generate_csv_students(output_path):
    content = "studentId,externalRef,firstName,lastName,email\n" \
              "10001,RA-88392,Ana,Souza,ana.souza@universidade.edu.br\n" \
              "10002,RA-11293,Carlos,Eduardo,carlos.eduardo@universidade.edu.br\n" \
              "10003,RA-49923,Beatriz,Melo,beatriz.melo@universidade.edu.br\n"
              
    with open(output_path, "w", encoding="utf-8") as f:
        f.write(content)
    print(f"CSV students template generated at {output_path}")

def main():
    templates_dir = Path("/home/calefi/Documentos/ProvaGen/client/public/templates")
    templates_dir.mkdir(parents=True, exist_ok=True)
    
    generate_xlsx_questions(templates_dir / "questoes-modelo.xlsx")
    generate_docx_questions(templates_dir / "questoes-modelo.docx")
    generate_json_questions(templates_dir / "questoes-modelo.json")
    
    generate_xlsx_students(templates_dir / "alunos-modelo.xlsx")
    generate_csv_students(templates_dir / "alunos-modelo.csv")
    
    print("All templates generated successfully!")

if __name__ == "__main__":
    main()
