import 'package:flutter/material.dart';

class PremiumLockWidget extends StatelessWidget {
  const PremiumLockWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      alignment: Alignment.center,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: const [
          Icon(Icons.lock, size: 40, color: Colors.amber),
          SizedBox(height: 8),
          Text(
            "Conteúdo Premium",
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          Text(
            "Assine para desbloquear",
            style: TextStyle(color: Colors.grey),
          ),
        ],
      ),
    );
  }
}