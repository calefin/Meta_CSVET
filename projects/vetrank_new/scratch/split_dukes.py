import os
import re
import unicodedata
import fitz  # PyMuPDF

pdf_path = r"D:\pdfs\Fisiologia\Fisiologia dos animais domésticos 13. ed Dukes.pdf"
output_dir = r"D:\pdfs\Fisiologia\Capitulos"

def sanitize_filename(name):
    # Remove acentos
    name = unicodedata.normalize('NFKD', name).encode('ascii', 'ignore').decode('ascii')
    # Substitui caracteres especiais por underline
    name = re.sub(r'[^a-zA-Z0-9\s-]', '', name).strip()
    name = re.sub(r'[\s-]+', '_', name)
    return name

def main():
    if not os.path.exists(pdf_path):
        print(f"Erro: PDF não encontrado em {pdf_path}")
        return

    os.makedirs(output_dir, exist_ok=True)
    
    print(f"Abrindo PDF: {pdf_path}")
    doc = fitz.open(pdf_path)
    total_pages = len(doc)
    print(f"Total de páginas no PDF: {total_pages}")
    
    toc = doc.get_toc()
    if not toc:
        print("Erro: Não foi possível obter o Sumário (TOC) do PDF.")
        return
        
    # Filtrar apenas capítulos (Nível 2 no TOC)
    chapters = [item for item in toc if item[0] == 2]
    print(f"Encontrados {len(chapters)} capítulos para divisão.")
    
    # Ordenar por número de página apenas por segurança
    chapters.sort(key=lambda x: x[2])
    
    for idx, chapter in enumerate(chapters):
        level, title, start_page = chapter
        
        # O próximo capítulo define o fim deste
        if idx < len(chapters) - 1:
            end_page = chapters[idx + 1][2] - 1
        else:
            end_page = total_pages
            
        # Ajusta para 0-based index usado pelo PyMuPDF
        # PyMuPDF usa indices 0-based, enquanto o TOC vem com 1-based
        start_idx = start_page - 1
        end_idx = end_page - 1
        
        # Garante limites corretos
        start_idx = max(0, start_idx)
        end_idx = min(total_pages - 1, end_idx)
        
        # Caso ocorra algum erro lógico nos índices
        if start_idx > end_idx:
            print(f"Aviso: Capítulo {title} tem intervalo inválido: pág {start_page} a {end_page}. Pulando.")
            continue
            
        # Formata o nome do arquivo de saída
        # Ex: "Capitulo_01_Tecido_Nervoso.pdf"
        clean_title = sanitize_filename(title)
        output_filename = f"Capitulo_{clean_title}.pdf"
        output_path = os.path.join(output_dir, output_filename)
        
        print(f"Criando [{idx+1}/{len(chapters)}]: {output_filename} (Páginas {start_page} a {end_page})")
        
        # Cria um novo documento PDF e insere as páginas correspondentes
        new_doc = fitz.open()
        new_doc.insert_pdf(doc, from_page=start_idx, to_page=end_idx)
        new_doc.save(output_path)
        new_doc.close()
        
    doc.close()
    print("\nDivisão concluída com sucesso!")
    print(f"Arquivos salvos em: {output_dir}")

if __name__ == "__main__":
    main()
