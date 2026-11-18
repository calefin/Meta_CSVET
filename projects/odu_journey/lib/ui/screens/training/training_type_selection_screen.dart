// lib/ui/screens/training/training_type_selection_screen.dart

import 'package:flutter/material.dart';
import 'package:odu_journey/ui/screens/training/odu_training_screen.dart';
import 'package:odu_journey/ui/screens/training/training_recognize_screen.dart';

class TrainingTypeSelectionScreen extends StatelessWidget {
  // Agora recebemos uma FLAG (verdadeiro/falso) em vez da lista
  final bool isMejiOnly;

  const TrainingTypeSelectionScreen({
    super.key, 
    required this.isMejiOnly, // true = Só Meji, false = Todos
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Escolha o Modo')),
      body: ListView(
        children: [
          ListTile(
            title: const Text('Reconhecer a caída'),
            subtitle: const Text('Veja uma caída e escolha a alternativa correta'),
            trailing: const Icon(Icons.arrow_forward_ios),
            onTap: () {
              // Passamos a flag para o treino
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => TrainingRecognizeScreen(isMejiOnly: isMejiOnly),
                ),
              );
            },
          ),
          ListTile(
            title: const Text('Digitar o nome'),
            subtitle: const Text('Veja uma caída e digite o nome do Odù'),
            trailing: const Icon(Icons.arrow_forward_ios),
            onTap: () {
              // Passamos a flag para o treino
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => OduTrainingScreen(isMejiOnly: isMejiOnly, isQuizMode: false),
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}