#!/usr/bin/env python3
"""
Pipeline Unificado: Auditoria de Lacunas para Revisões Sistemáticas e Meta-Análises.
Executa:
1. Auditoria da literatura (Revisões existentes vs acúmulo de estudos primários)
2. Cálculo do Índice de Oportunidade de Revisão (IRO)
3. Geração do Protocolo PRISMA-P, Dossiê em Markdown e Referências BibTeX para triagem no Rayyan
"""

import sys
import os
import argparse
import subprocess

SCRIPT_DIR = os.path.dirname(os.path.realpath(__file__))


def main():
    parser = argparse.ArgumentParser(description="Audita lacunas e gera protocolo PRISMA para Revisões Sistemáticas e Meta-Análises.")
    parser.add_argument("-q", "--query", required=True, help="Tema ou pergunta da revisão sistemática")
    parser.add_argument("-o", "--output-dir", default="./output_meta_analysis_gap", help="Diretório onde os relatórios serão gerados")
    parser.add_argument("--limit-reviews", type=int, default=25, help="Limite de busca para revisões existentes")
    parser.add_argument("--limit-primary", type=int, default=50, help="Limite de busca para estudos primários")

    args = parser.parse_args()

    os.makedirs(args.output_dir, exist_ok=True)
    audit_json = os.path.join(args.output_dir, "audit_review.json")
    dossier_md = os.path.join(args.output_dir, "dossie_revisao_sistematica.md")
    studies_bib = os.path.join(args.output_dir, "estudos_primarios_candidatos.bib")

    print("\n" + "="*70)
    print(" AUDITORIA DE LACUNAS DE META-ANÁLISES E REVISÕES SISTEMÁTICAS")
    print("="*70)

    # 1. Audit
    audit_cmd = [
        sys.executable, os.path.join(SCRIPT_DIR, "audit_systematic_reviews.py"),
        "-q", args.query,
        "-o", audit_json,
        "--limit-reviews", str(args.limit_reviews),
        "--limit-primary", str(args.limit_primary)
    ]
    res1 = subprocess.run(audit_cmd)
    if res1.returncode != 0:
        print("[!] Erro durante a auditoria de revisões.", file=sys.stderr)
        sys.exit(res1.returncode)

    # 2. Protocol & Dossier
    protocol_cmd = [
        sys.executable, os.path.join(SCRIPT_DIR, "generate_prisma_protocol.py"),
        "-i", audit_json,
        "-m", dossier_md,
        "-b", studies_bib
    ]
    res2 = subprocess.run(protocol_cmd)
    if res2.returncode != 0:
        print("[!] Erro durante a geração do protocolo PRISMA.", file=sys.stderr)
        sys.exit(res2.returncode)

    print("\n" + "="*70)
    print(f"[✓] PROCESSO CONCLUÍDO COM SUCESSO!")
    print(f"Arquivos salvos em: {os.path.abspath(args.output_dir)}/")
    print(f"  ├─ Dossiê & Protocolo PRISMA-P: {dossier_md}")
    print(f"  ├─ Estudos Primários para Triagem (BibTeX): {studies_bib}")
    print(f"  └─ Dados Brutos da Auditoria (JSON): {audit_json}")
    print("="*70 + "\n")


if __name__ == "__main__":
    main()
