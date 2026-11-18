import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/app_models.dart';
import '../services/app_state.dart';
import '../theme/app_theme.dart';
import 'edit_key_screen.dart';
import 'scan_paper_screen.dart';
import 'review_papers_screen.dart';
import 'item_analysis_screen.dart';

class QuizDetailScreen extends StatefulWidget {
  final String quizId;
  const QuizDetailScreen({Key? key, required this.quizId}) : super(key: key);

  @override
  State<QuizDetailScreen> createState() => _QuizDetailScreenState();
}

class _QuizDetailScreenState extends State<QuizDetailScreen> {
  final AppState _appState = AppState();

  @override
  void initState() {
    super.initState();
    _appState.addListener(_update);
  }

  @override
  void dispose() {
    _appState.removeListener(_update);
    super.dispose();
  }

  void _update() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    // Locate quiz
    final quiz = _appState.quizzes.firstWhere(
      (q) => q.id == widget.quizId,
      orElse: () => Quiz(id: '', name: 'Desconhecido', gabaritoTemplate: '', date: DateTime.now(), institutionId: '', classId: '', questionsCount: 0, totalPoints: 0, correctedExamsCount: 0, questionKeys: []),
    );

    if (quiz.id.isEmpty) {
      return Scaffold(
        appBar: AppBar(title: const Text('Quiz não encontrado')),
        body: const Center(child: Text('Quiz não encontrado no banco local.')),
      );
    }

    final institution = _appState.institutions.firstWhere(
      (i) => i.id == quiz.institutionId,
      orElse: () => Institution(id: '', name: 'Instituição Desconhecida'),
    );

    final classObj = _appState.classes.firstWhere(
      (c) => c.id == quiz.classId,
      orElse: () => Class(id: '', name: 'Turma Desconhecida', institutionId: ''),
    );

    return Scaffold(
      appBar: AppBar(
        title: Text(quiz.name),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Top Header Card with violet-indigo gradient
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: AppTheme.primaryGradient,
                borderRadius: BorderRadius.circular(24),
                boxShadow: [
                  BoxShadow(
                    color: AppTheme.primary.withOpacity(0.3),
                    blurRadius: 16,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          quiz.name,
                          style: const TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.bold,
                            color: Colors.white,
                          ),
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: Colors.white24,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          '${quiz.questionsCount} Qs',
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'IES: ${institution.name}',
                    style: TextStyle(color: Colors.white.withOpacity(0.9), fontSize: 14),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Turma: ${classObj.name}',
                    style: TextStyle(color: Colors.white.withOpacity(0.9), fontSize: 14),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Gabarito Associado: ${quiz.gabaritoTemplate}',
                    style: const TextStyle(color: Colors.white70, fontSize: 13),
                  ),
                  const Divider(height: 24, color: Colors.white24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      _buildHeaderStatItem('Corrigidas', '${quiz.correctedExamsCount}'),
                      _buildHeaderStatItem('Pontos Máximos', '${quiz.totalPoints.toStringAsFixed(1)} pts'),
                      _buildHeaderStatItem('Data', DateFormat('dd/MM/yyyy').format(quiz.date)),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            const Text(
              'Ações Disponíveis',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
            ),
            const SizedBox(height: 12),

            // Action Grid
            GridView.count(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              crossAxisCount: 2,
              crossAxisSpacing: 12,
              mainAxisSpacing: 12,
              childAspectRatio: 1.25,
              children: [
                _buildActionButton(
                  icon: Icons.camera_alt,
                  title: 'Escanear Prova',
                  subtitle: 'Corrigir na câmera',
                  color: AppTheme.secondary,
                  onTap: () {
                    final status = _appState.subscriptionStatus.toLowerCase();
                    if (status.contains('expirad') || status.contains('inativ')) {
                      _showSubscriptionRequiredDialog(context);
                      return;
                    }
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => ScanPaperScreen(quizId: quiz.id),
                      ),
                    );
                  },
                ),
                _buildActionButton(
                  icon: Icons.vpn_key,
                  title: 'Editar Gabarito',
                  subtitle: 'Gabarito oficial',
                  color: AppTheme.primary,
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => EditKeyScreen(quizId: quiz.id),
                      ),
                    );
                  },
                ),
                _buildActionButton(
                  icon: Icons.reviews,
                  title: 'Revisar Provas',
                  subtitle: 'Ver notas e erros',
                  color: AppTheme.success,
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => ReviewPapersScreen(quizId: quiz.id),
                      ),
                    );
                  },
                ),
                _buildActionButton(
                  icon: Icons.analytics,
                  title: 'Estatísticas',
                  subtitle: 'Análise de acertos',
                  color: AppTheme.warning,
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => ItemAnalysisScreen(quizId: quiz.id),
                      ),
                    );
                  },
                ),
              ],
            ),
            const SizedBox(height: 24),

            // Quick Stats section (Guidance for teachers)
            const Text(
              'Estatísticas Rápidas & Orientação',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
            ),
            const SizedBox(height: 12),
            _buildStatsOverviewCard(quiz.id),
            const SizedBox(height: 16),
          ],
        ),
      ),
    );
  }

  Widget _buildHeaderStatItem(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: const TextStyle(color: Colors.white60, fontSize: 10, fontWeight: FontWeight.bold, letterSpacing: 0.5),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.bold),
        ),
      ],
    );
  }

  Widget _buildActionButton({
    required IconData icon,
    required String title,
    required String subtitle,
    required Color color,
    required VoidCallback onTap,
  }) {
    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: color.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, color: color, size: 24),
              ),
              const SizedBox(height: 12),
              Text(
                title,
                style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStatsOverviewCard(String quizId) {
    final quiz = _appState.quizzes.firstWhere((q) => q.id == quizId);
    return FutureBuilder<List<CorrectedPaper>>(
      future: _appState.getCorrectedPapers(quizId),
      builder: (context, snapshot) {
        if (!snapshot.hasData || snapshot.data!.isEmpty) {
          return Container(
            padding: const EdgeInsets.all(20),
            width: double.infinity,
            decoration: BoxDecoration(
              color: AppTheme.cardBg,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.white.withOpacity(0.04)),
            ),
            child: const Row(
              children: [
                Icon(Icons.info_outline, color: AppTheme.textSecondary),
                SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Nenhuma prova corrigida para este quiz ainda. Use o botão "Escanear Prova" para começar a analisar o desempenho.',
                    style: TextStyle(color: AppTheme.textSecondary, fontSize: 13),
                  ),
                ),
              ],
            ),
          );
        }

        final papers = snapshot.data!;
        
        // Compute stats
        double totalSum = 0;
        double maxGrade = 0;
        double minGrade = quiz.totalPoints;
        int passCount = 0;

        for (var p in papers) {
          totalSum += p.grade;
          if (p.grade > maxGrade) maxGrade = p.grade;
          if (p.grade < minGrade) minGrade = p.grade;
          if (p.grade >= quiz.totalPoints * 0.6) passCount++;
        }

        final avg = totalSum / papers.length;
        final passPercent = (passCount / papers.length) * 100;

        return Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppTheme.cardBg,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: Colors.white.withOpacity(0.05)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Média da Turma:', style: TextStyle(color: AppTheme.textSecondary)),
                  Text(
                    avg.toStringAsFixed(2),
                    style: TextStyle(
                      color: avg >= quiz.totalPoints * 0.6 ? AppTheme.success : AppTheme.error,
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Taxa de Aprovação (>= ${(quiz.totalPoints * 0.6).toStringAsFixed(1)}):', style: const TextStyle(color: AppTheme.textSecondary)),
                  Text(
                    '${passPercent.toStringAsFixed(0)}%',
                    style: TextStyle(
                      color: passPercent >= 70.0 ? AppTheme.success : AppTheme.warning,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Maior Nota / Menor Nota:', style: TextStyle(color: AppTheme.textSecondary)),
                  Text(
                    '${maxGrade.toStringAsFixed(1)} / ${minGrade.toStringAsFixed(1)}',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              const Divider(height: 24, color: Colors.white10),
              const Text(
                'Orientação Pedagógica:',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.secondary),
              ),
              const SizedBox(height: 6),
              Text(
                avg >= quiz.totalPoints * 0.75
                    ? 'Excelente! A turma demonstrou sólido domínio do conteúdo avaliado. Prossiga com o cronograma regular.'
                    : avg >= quiz.totalPoints * 0.6
                        ? 'Desempenho regular. Recomenda-se revisar as questões críticas mostradas em "Estatísticas" onde mais alunos erraram.'
                        : 'Atenção! A média da turma está abaixo do esperado. Sugere-se realizar uma aula de reforço focada nos tópicos avaliados.',
                style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary, height: 1.4),
              ),
            ],
          ),
        );
      },
    );
  }

  void _showSubscriptionRequiredDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          backgroundColor: AppTheme.surface,
          title: const Row(
            children: [
              Icon(Icons.lock, color: Colors.amber),
              SizedBox(width: 8),
              Text('Assinatura Requerida'),
            ],
          ),
          content: const Text(
            'Sua assinatura do plano Prof-Aid expirou ou está inativa. A funcionalidade de escaneamento e correção de provas por câmera com OpenCV requer uma assinatura ativa.\n\nPor favor, valide sua assinatura online ou gerencie seu plano na aba "Conta".',
            style: TextStyle(color: Colors.white70),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Cancelar', style: TextStyle(color: AppTheme.textSecondary)),
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
              onPressed: () {
                Navigator.pop(context);
              },
              child: const Text('Entendi', style: TextStyle(color: Colors.white)),
            ),
          ],
        );
      },
    );
  }
}
