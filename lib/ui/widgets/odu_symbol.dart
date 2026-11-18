import 'package:flutter/material.dart';

class OduSymbol extends StatelessWidget {
  final List<int> right;
  final List<int> left;
  final Color color;

  const OduSymbol({
    super.key,
    required List<int>? right,
    required List<int>? left,
    this.color = Colors.black,
  })  : right = right ?? const [1, 1, 1, 1],
        left = left ?? const [1, 1, 1, 1];

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      painter: _OduPainter(right: right, left: left, color: color),
      child: Container(),
    );
  }
}

class _OduPainter extends CustomPainter {
  final List<int> right;
  final List<int> left;
  final Color color;

  _OduPainter({required this.right, required this.left, required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = size.width * 0.06
      ..strokeCap = StrokeCap.round;

    final marginY = size.height * 0.1;
    final gap = (size.height - (marginY * 2)) / 3; 
    
    // DEFINIÇÃO VISUAL FIXA:
    // Esquerda da tela (25%)
    final xLeft = size.width * 0.25; 
    // Direita da tela (75%)
    final xRight = size.width * 0.75; 
    
    final lineHalfHeight = gap * 0.35;

    for (var i = 0; i < 4; i++) {
      final y = marginY + (i * gap);
      
      // LÓGICA DIRETA (SEM INVERSÃO):
      // O dado que vem em 'left' desenha no xLeft (Esquerda Visual)
      final leftCount = (i < left.length) ? left[i] : 1;
      _drawMark(canvas, paint, xLeft, y, lineHalfHeight, size.width, leftCount);

      // O dado que vem em 'right' desenha no xRight (Direita Visual)
      final rightCount = (i < right.length) ? right[i] : 1;
      _drawMark(canvas, paint, xRight, y, lineHalfHeight, size.width, rightCount);
    }
  }

  void _drawMark(Canvas canvas, Paint paint, double cx, double cy, double lh, double width, int count) {
    final double spacing = width * 0.14; 

    if (count == 1) {
      canvas.drawLine(Offset(cx, cy - lh), Offset(cx, cy + lh), paint);
    } else {
      canvas.drawLine(Offset(cx - (spacing / 2), cy - lh), Offset(cx - (spacing / 2), cy + lh), paint);
      canvas.drawLine(Offset(cx + (spacing / 2), cy - lh), Offset(cx + (spacing / 2), cy + lh), paint);
    }
  }

  @override
  bool shouldRepaint(covariant _OduPainter oldDelegate) {
    return oldDelegate.right != right || oldDelegate.left != left || oldDelegate.color != color;
  }
}