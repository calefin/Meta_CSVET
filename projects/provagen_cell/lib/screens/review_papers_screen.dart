import 'dart:io';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/app_models.dart';
import '../services/app_state.dart';
import '../theme/app_theme.dart';

class ReviewPapersScreen extends StatefulWidget {
  final String quizId;
  const ReviewPapersScreen({Key? key, required this.quizId}) : super(key: key);

  @override
  State<ReviewPapersScreen> createState() => _ReviewPapersScreenState();
}

class _ReviewPapersScreenState extends State<ReviewPapersScreen> {
  final AppState _appState = AppState();
  List<CorrectedPaper> _papers = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadPapers();
  }

  Future<void> _loadPapers() async {
    final list = await _appState.getCorrectedPapers(widget.quizId);
    setState(() {
      _papers = list;
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final quiz = _appState.quizzes.firstWhere((q) => q.id == widget.quizId);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Revisar Provas'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _papers.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.assignment_turned_in_outlined, size: 64, color: AppTheme.textSecondary.withOpacity(0.5)),
                      const SizedBox(height: 16),
                      const Text(
                        'Nenhuma prova corrigida para este quiz.',
                        style: TextStyle(color: AppTheme.textSecondary, fontSize: 16),
                      ),
                    ],
                  ),
                )
              : Column(
                  children: [
                    // Summary header
                    Container(
                      padding: const EdgeInsets.all(16),
                      color: AppTheme.surface,
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Total de Provas: ${_papers.length}',
                            style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                          ),
                          Text(
                            'Pontuação Máxima: ${quiz.totalPoints.toStringAsFixed(1)} pts',
                            style: const TextStyle(color: AppTheme.textSecondary),
                          ),
                        ],
                      ),
                    ),
                    const Divider(height: 1, color: Colors.white10),
                    
                    // List
                    Expanded(
                      child: ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: _papers.length,
                        itemBuilder: (context, index) {
                          final paper = _papers[index];
                          final isApproved = paper.grade >= 6.0;

                          return Card(
                            margin: const EdgeInsets.only(bottom: 10),
                            child: Material(
                              color: Colors.transparent,
                              child: ListTile(
                                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                                leading: CircleAvatar(
                                  backgroundColor: isApproved
                                      ? AppTheme.success.withOpacity(0.15)
                                      : AppTheme.error.withOpacity(0.15),
                                  child: Icon(
                                    isApproved ? Icons.check : Icons.close,
                                    color: isApproved ? AppTheme.success : AppTheme.error,
                                  ),
                                ),
                                title: Text(
                                  paper.studentName,
                                  style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                                ),
                                subtitle: Text(
                                  'Data: ${DateFormat('dd/MM/yyyy HH:mm').format(paper.scannedAt)}',
                                  style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                                ),
                                trailing: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                                  decoration: BoxDecoration(
                                    color: isApproved
                                        ? AppTheme.success.withOpacity(0.12)
                                        : AppTheme.error.withOpacity(0.12),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Text(
                                    paper.grade.toStringAsFixed(1),
                                    style: TextStyle(
                                      color: isApproved ? AppTheme.success : AppTheme.error,
                                      fontWeight: FontWeight.bold,
                                      fontSize: 15,
                                    ),
                                  ),
                                ),
                                onTap: () => _showPaperDetails(context, quiz, paper),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                  ],
                ),
    );
  }

  void _showPaperDetails(BuildContext context, Quiz quiz, CorrectedPaper paper) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return DraggableScrollableSheet(
          initialChildSize: 0.85,
          minChildSize: 0.5,
          maxChildSize: 0.95,
          builder: (context, scrollController) {
            return Container(
              decoration: const BoxDecoration(
                color: AppTheme.surface,
                borderRadius: BorderRadius.only(
                  topLeft: Radius.circular(24),
                  topRight: Radius.circular(24),
                ),
              ),
              child: ListView(
                controller: scrollController,
                padding: const EdgeInsets.all(20),
                children: [
                  // Grabber handle
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      margin: const EdgeInsets.only(bottom: 20),
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.2),
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  
                  // Header
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              paper.studentName,
                              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.white),
                            ),
                            Text(
                              'RM: ${paper.studentId.toUpperCase()}',
                              style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
                            ),
                          ],
                        ),
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            'Nota: ${paper.grade.toStringAsFixed(1)}',
                            style: TextStyle(
                              color: paper.grade >= 6.0 ? AppTheme.success : AppTheme.error,
                              fontSize: 20,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Text(
                            'Acertos: ${paper.correctCount}/${quiz.questionsCount}',
                            style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  const Divider(height: 1, color: Colors.white10),
                  const SizedBox(height: 16),
                  
                  // Low-resolution visual record of student's exam sheet
                  if (paper.correctionMaskPath != null && paper.correctionMaskPath!.isNotEmpty) ...[
                    const Text(
                      'Registro Visual da Correção',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                    ),
                    const SizedBox(height: 8),
                    FutureBuilder<bool>(
                      future: File(paper.correctionMaskPath!).exists(),
                      builder: (context, snapshot) {
                        if (snapshot.data == true) {
                          return Container(
                            height: 220,
                            width: double.infinity,
                            decoration: BoxDecoration(
                              color: Colors.black26,
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: Colors.white.withOpacity(0.08)),
                            ),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(15),
                              child: GestureDetector(
                                onTap: () {
                                  // Open full screen image viewer
                                  Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (context) => Scaffold(
                                        appBar: AppBar(
                                          title: Text(paper.studentName),
                                          backgroundColor: Colors.black,
                                        ),
                                        backgroundColor: Colors.black,
                                        body: Center(
                                          child: InteractiveViewer(
                                            child: Image.file(
                                              File(paper.correctionMaskPath!),
                                              fit: BoxFit.contain,
                                            ),
                                          ),
                                        ),
                                      ),
                                    ),
                                  );
                                },
                                child: Stack(
                                  alignment: Alignment.center,
                                  children: [
                                    Image.file(
                                      File(paper.correctionMaskPath!),
                                      width: double.infinity,
                                      height: double.infinity,
                                      fit: BoxFit.cover,
                                    ),
                                    Container(
                                      color: Colors.black38,
                                      alignment: Alignment.center,
                                      child: const Column(
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [
                                          Icon(Icons.zoom_in, color: Colors.white, size: 36),
                                          SizedBox(height: 4),
                                          Text(
                                            'Toque para ampliar e ver detalhes',
                                            style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        } else {
                          return const Text(
                            'Arquivo de imagem não encontrado localmente.',
                            style: TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                          );
                        }
                      },
                    ),
                    const SizedBox(height: 20),
                    const Divider(height: 1, color: Colors.white10),
                    const SizedBox(height: 16),
                  ],

                  const Text(
                    'Detalhamento das Questões',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                  ),
                  const SizedBox(height: 12),
                  
                  // Question items list
                  ListView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: quiz.questionsCount,
                    itemBuilder: (context, index) {
                      final qNum = index + 1;
                      final studentAns = paper.answers[qNum] ?? '';
                      final key = quiz.questionKeys.firstWhere(
                        (k) => k.questionNumber == qNum,
                        orElse: () => QuestionKey(questionNumber: qNum, correctOption: 'A', points: 0.0),
                      );

                      bool isCorrect = false;
                      bool isCancelled = key.isCancelled;
                      
                      if (isCancelled) {
                        isCorrect = true;
                      } else {
                        isCorrect = studentAns == key.correctOption;
                      }

                      return Container(
                        margin: const EdgeInsets.only(bottom: 8),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppTheme.cardBg,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                            color: isCancelled
                                ? AppTheme.warning.withOpacity(0.2)
                                : isCorrect
                                    ? AppTheme.success.withOpacity(0.15)
                                    : AppTheme.error.withOpacity(0.15),
                            width: 1,
                          ),
                        ),
                        child: Row(
                          children: [
                            Container(
                              width: 32,
                              height: 32,
                              alignment: Alignment.center,
                              decoration: BoxDecoration(
                                color: isCancelled
                                    ? AppTheme.warning.withOpacity(0.15)
                                    : isCorrect
                                        ? AppTheme.success.withOpacity(0.15)
                                        : AppTheme.error.withOpacity(0.15),
                                shape: BoxShape.circle,
                              ),
                              child: Text(
                                '$qNum',
                                style: TextStyle(
                                  fontWeight: FontWeight.bold,
                                  color: isCancelled
                                      ? AppTheme.warning
                                      : isCorrect
                                          ? AppTheme.success
                                          : AppTheme.error,
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (isCancelled)
                                    Row(
                                      children: [
                                        const Text('ANULADA', style: TextStyle(color: AppTheme.warning, fontWeight: FontWeight.bold, fontSize: 13)),
                                        const SizedBox(width: 6),
                                        Text(
                                          key.redistributePoints ? '(Pontos Redistribuídos)' : '(Ponto Dado a Todos)',
                                          style: const TextStyle(color: AppTheme.textSecondary, fontSize: 11),
                                        ),
                                      ],
                                    )
                                  else
                                    Row(
                                      children: [
                                        Text('Resp: ', style: TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                          decoration: BoxDecoration(
                                            color: isCorrect ? AppTheme.success.withOpacity(0.2) : AppTheme.error.withOpacity(0.2),
                                            borderRadius: BorderRadius.circular(4),
                                          ),
                                          child: Text(
                                            studentAns.isEmpty ? 'Vazia' : studentAns,
                                            style: TextStyle(
                                              color: isCorrect ? AppTheme.success : AppTheme.error,
                                              fontWeight: FontWeight.bold,
                                              fontSize: 13,
                                            ),
                                          ),
                                        ),
                                        if (!isCorrect) ...[
                                          const SizedBox(width: 8),
                                          const Icon(Icons.arrow_forward, color: AppTheme.textSecondary, size: 14),
                                          const SizedBox(width: 8),
                                          const Text('Gabarito: ', style: TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                            decoration: BoxDecoration(
                                              color: AppTheme.success.withOpacity(0.2),
                                              borderRadius: BorderRadius.circular(4),
                                            ),
                                            child: Text(
                                              key.correctOption,
                                              style: const TextStyle(color: AppTheme.success, fontWeight: FontWeight.bold, fontSize: 13),
                                            ),
                                          ),
                                        ],
                                      ],
                                    ),
                                ],
                              ),
                            ),
                            Text(
                              isCorrect 
                                  ? '+${key.points.toStringAsFixed(2)} pt' 
                                  : '0.0 pt',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                color: isCorrect ? AppTheme.success : AppTheme.textSecondary,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                  const SizedBox(height: 24),
                ],
              ),
            );
          },
        );
      },
    );
  }
}
