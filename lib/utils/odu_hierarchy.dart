// lib/utils/odu_hierarchy.dart

import 'package:odu_journey/data/models/odu.dart';

class OduHierarchy {
  
  // Ordem Fixa de Senioridade das Pernas (1 = Mais Velho/Rei, 16 = Mais Novo)
  static int getLegSeniority(List<int> leg) {
    final str = leg.join();
    switch (str) {
      case '1111': return 1;  // Ogbe
      case '2222': return 2;  // Oyeku
      case '2112': return 3;  // Iwori
      case '1221': return 4;  // Odi
      case '1122': return 5;  // Irosun
      case '2211': return 6;  // Owonrin
      case '1222': return 7;  // Obara
      case '2221': return 8;  // Okanran
      case '1112': return 9;  // Ogunda
      case '2111': return 10; // Osa
      case '2122': return 11; // Ika
      case '2212': return 12; // Oturupon
      case '1211': return 13; // Otura
      case '1121': return 14; // Irete
      case '1212': return 15; // Ose
      case '2121': return 16; // Ofun
      default: return 99; // Erro
    }
  }

  // Verifica se o Odu é um Meji (Perna Direita == Esquerda)
  static bool isMeji(Odu odu) {
    return odu.right.toString() == odu.left.toString();
  }

  /// Retorna TRUE se o Odu A for MAIS VELHO que o Odu B.
  static bool isSenior(Odu a, Odu b) {
    bool aMeji = isMeji(a);
    bool bMeji = isMeji(b);

    // REGRA 1: Meji sempre ganha de Divisão (Omoluo)
    if (aMeji && !bMeji) return true;  // A é Rei, B é comum -> A ganha
    if (!aMeji && bMeji) return false; // A é comum, B é Rei -> B ganha

    // REGRA 2: Se ambos são iguais (ambos Meji ou ambos Divisão),
    // desempatamos pela perna direita (índice menor = mais velho).
    int rightA = getLegSeniority(a.right);
    int rightB = getLegSeniority(b.right);

    if (rightA < rightB) return true; 
    if (rightA > rightB) return false; 

    // REGRA 3: Se as direitas são iguais, desempatamos pela esquerda
    int leftA = getLegSeniority(a.left);
    int leftB = getLegSeniority(b.left);

    return leftA < leftB;
  }

  /// Determina se a tiragem é IRE ou IBI
  static bool isIre(Odu main, Odu rightWitness, Odu leftWitness) {
    // Exceção: Se o Odu principal for "Obara alguma coisa" (Direita = Obara/7) -> Sempre Ire
    if (getLegSeniority(main.right) == 7) {
      return true; 
    }

    // Regra Padrão: Se Direita for mais velha que Esquerda -> IRE
    return isSenior(rightWitness, leftWitness);
  }
}