import fitz  # PyMuPDF

pdf_path = r"D:\pdfs\Fisiologia\Fisiologia dos animais domésticos 13. ed Dukes.pdf"

try:
    print(f"Abrindo o PDF: {pdf_path}")
    doc = fitz.open(pdf_path)
    toc = doc.get_toc()
    
    if toc:
        print(f"\nEncontrados {len(toc)} itens no Sumário (TOC):")
        for item in toc[:100]:  # Mostrar os primeiros 100 itens
            # item = [level, title, page_number]
            print(f"Nível {item[0]} | {item[1]} | Página {item[2]}")
        if len(toc) > 100:
            print(f"... e mais {len(toc) - 100} itens.")
    else:
        print("\nO PDF não possui um Sumário (TOC) embutido.")
        
        # Vamos extrair o texto das primeiras páginas (índice) para analisar
        print("\nExtraindo texto das primeiras 15 páginas para análise do sumário:")
        for i in range(min(15, len(doc))):
            text = doc[i].get_text()
            if "sumário" in text.lower() or "conteúdo" in text.lower() or "capítulo" in text.lower():
                print(f"\n--- Página {i+1} ---")
                print(text[:2000])  # Mostrar os primeiros 2000 caracteres
except Exception as e:
    print("Erro:", e)
