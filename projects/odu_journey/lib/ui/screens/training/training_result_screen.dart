// lib/ui/screens/training/training_result_screen.dart

import 'package:flutter/material.dart';

class TrainingResultScreen extends StatelessWidget {
  final int score;
  final int totalQuestions;
  final int xpEarned;

  const TrainingResultScreen({
    super.key,
    required this.score,
    required this.totalQuestions,
    required this.xpEarned,
  });

  @override
  Widget build(BuildContext context) {
    // Cálculo da porcentagem de acerto
    final double percentage = score / totalQuestions;
    
    // Define mensagem e cor baseada no desempenho
    String message;
    IconData icon;
    Color color;

    if (percentage == 1.0) {
      message = "Perfeito! Você é um mestre!";
      icon = Icons.stars;
      color = Colors.amber;
    } else if (percentage >= 0.7) {
      message = "Muito bem! Continue assim.";
      icon = Icons.thumb_up;
      color = Colors.green;
    } else if (percentage >= 0.4) {
      message = "Bom treino. Vamos melhorar?";
      icon = Icons.sentiment_satisfied;
      color = Colors.blue;
    } else {
      message = "Não desanime! O estudo é contínuo.";
      icon = Icons.school;
      color = Colors.orange;
    }

    return Scaffold(
      backgroundColor: Colors.white,
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 80, color: color),
              const SizedBox(height: 24),
              Text(
                "Resultado do Treino",
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: Colors.black87
                ),
              ),
              const SizedBox(height: 16),
              Text(
                "$score / $totalQuestions",
                style: TextStyle(fontSize: 48, fontWeight: FontWeight.bold, color: color),
              ),
              const SizedBox(height: 8),
              Text(
                "Acertos",
                style: TextStyle(fontSize: 16, color: Colors.grey[600]),
              ),
              const SizedBox(height: 32),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.deepPurple.shade50,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(
                  children: [
                    Text(
                      "+ $xpEarned XP",
                      style: const TextStyle(
                        fontSize: 24, 
                        fontWeight: FontWeight.bold, 
                        color: Colors.deepPurple
                      ),
                    ),
                    const SizedBox(height: 4),
                    const Text("Para desbloquear níveis"),
                  ],
                ),
              ),
              const SizedBox(height: 32),
              Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 18),
              ),
              const SizedBox(height: 48),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    backgroundColor: Colors.deepPurple,
                    foregroundColor: Colors.white,
                  ),
                  onPressed: () {
                    // Volta para a tela de opções (fecha o treino e o resultado)
                    Navigator.popUntil(context, ModalRoute.withName('/training'));
                  },
                  child: const Text("CONCLUIR", style: TextStyle(fontSize: 16)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}