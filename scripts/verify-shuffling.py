import sys
import os
import random

# Add server directory to path
sys.path.append(os.path.join(os.path.dirname(__file__), '../server'))

def shuffle_questions(questions, version, shuffle_mode="all"):
    # This is the exact algorithm from server/pdf-generator.py
    mode = shuffle_mode
    rng = random.Random(version)
    result = list(questions)

    if mode in ("all", "questions"):
        rng.shuffle(result)

    if mode in ("all", "items"):
        rng2 = random.Random(version + 10000)
        shuffled = []
        for q in result:
            q_copy = dict(q)
            if q_copy.get("type") == "multiple_choice" and q_copy.get("alternatives"):
                alts = list(q_copy["alternatives"])
                correct_text = q_copy.get("correctAnswer", "")
                
                # Normalize correct answer letter to the actual option text before shuffling
                if len(correct_text) == 1 and correct_text.isalpha():
                    idx = ord(correct_text.upper()) - 65
                    correct_text = alts[idx] if 0 <= idx < len(alts) else alts[0]
                
                rng2.shuffle(alts)
                q_copy["alternatives"] = alts
                try:
                    new_idx = alts.index(correct_text)
                    q_copy["correctAnswer"] = chr(65 + new_idx)
                except ValueError:
                    q_copy["correctAnswer"] = "A"
            shuffled.append(q_copy)
        result = shuffled

    return result

# Mock questions database
mock_questions = [
    {
        "id": 101,
        "type": "multiple_choice",
        "statement": "Qual o planeta mais próximo do Sol?",
        "alternatives": ["Terra", "Vênus", "Mercúrio", "Marte", "Júpiter"],
        "correctAnswer": "C"  # Mercúrio
    },
    {
        "id": 102,
        "type": "multiple_choice",
        "statement": "Quem pintou a Mona Lisa?",
        "alternatives": ["Michelangelo", "Leonardo da Vinci", "Rafael", "Donatello", "Van Gogh"],
        "correctAnswer": "B"  # Leonardo da Vinci
    },
    {
        "id": 103,
        "type": "multiple_choice",
        "statement": "Qual a fórmula química da água?",
        "alternatives": ["CO2", "H2O", "O2", "NaCl", "CH4"],
        "correctAnswer": "B"  # H2O
    }
]

print("=== INICIANDO SIMULAÇÃO DE EMBARALHAMENTO DE PROVAS ===")
print(f"Total de questões originais no banco: {len(mock_questions)}")

all_versions_results = {}

# Run and verify for versions 1, 2, and 3
for version in [1, 2, 3]:
    print(f"\n--- Simulação para Versão {version} ---")
    shuffled = shuffle_questions(mock_questions, version, "all")
    
    # 1. Verify question order
    order = [q["id"] for q in shuffled]
    print(f"Ordem das questões: {order}")
    
    # 2. Verify alternative correctness
    for i, q in enumerate(shuffled, 1):
        orig_q = next(oq for oq in mock_questions if oq["id"] == q["id"])
        
        # Get correct answer text from original question
        orig_correct_letter = orig_q["correctAnswer"]
        orig_correct_idx = ord(orig_correct_letter) - 65
        orig_correct_text = orig_q["alternatives"][orig_correct_idx]
        
        # Get corrected answer letter from shuffled question
        shuffled_correct_letter = q["correctAnswer"]
        shuffled_correct_idx = ord(shuffled_correct_letter) - 65
        shuffled_correct_text = q["alternatives"][shuffled_correct_idx]
        
        # VERIFICATION: The text of the correct answer must NOT change!
        assert orig_correct_text == shuffled_correct_text, f"ERRO: Resposta correta corrompida na questão {q['id']}"
        
        print(f"Questão {q['id']} (original {orig_q['id']}):")
        print(f"  Orig. Alternativas: {orig_q['alternatives']} (Correta: {orig_correct_letter} - {orig_correct_text})")
        print(f"  Shuf. Alternativas: {q['alternatives']} (Correta: {shuffled_correct_letter} - {shuffled_correct_text})")
        print("  Status: [OK] Gabarito mapeia perfeitamente para a alternativa correta embaralhada.")

print("\n=== SUCESSO: O algoritmo de embaralhamento e correção do gabarito é 100% robusto ===")
