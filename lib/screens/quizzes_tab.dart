import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/app_models.dart';
import '../services/app_state.dart';
import '../theme/app_theme.dart';
import 'quiz_detail_screen.dart';

class QuizzesTab extends StatefulWidget {
  const QuizzesTab({Key? key}) : super(key: key);

  @override
  State<QuizzesTab> createState() => _QuizzesTabState();
}

class _QuizzesTabState extends State<QuizzesTab> {
  final AppState _appState = AppState();
  String _sortCriteria = 'date'; // 'name', 'corrected', 'date'
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _appState.addListener(_updateState);
  }

  @override
  void dispose() {
    _appState.removeListener(_updateState);
    super.dispose();
  }

  void _updateState() {
    if (mounted) setState(() {});
  }

  List<Quiz> _getSortedQuizzes() {
    List<Quiz> filtered = _appState.quizzes.where((q) {
      final nameMatches = q.name.toLowerCase().contains(_searchQuery.toLowerCase());
      final inst = _appState.institutions.firstWhere((i) => i.id == q.institutionId, orElse: () => Institution(id: '', name: ''));
      final classObj = _appState.classes.firstWhere((c) => c.id == q.classId, orElse: () => Class(id: '', name: '', institutionId: ''));
      final instMatches = inst.name.toLowerCase().contains(_searchQuery.toLowerCase());
      final classMatches = classObj.name.toLowerCase().contains(_searchQuery.toLowerCase());
      return nameMatches || instMatches || classMatches;
    }).toList();

    if (_sortCriteria == 'name') {
      filtered.sort((a, b) => a.name.toLowerCase().compareTo(b.name.toLowerCase()));
    } else if (_sortCriteria == 'corrected') {
      filtered.sort((a, b) => b.correctedExamsCount.compareTo(a.correctedExamsCount));
    } else if (_sortCriteria == 'date') {
      filtered.sort((a, b) => b.date.compareTo(a.date));
    }
    return filtered;
  }

  @override
  Widget build(BuildContext context) {
    final sortedQuizzes = _getSortedQuizzes();

    return Scaffold(
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Search & Filter header
          Row(
            children: [
              Expanded(
                child: TextField(
                  onChanged: (val) {
                    setState(() {
                      _searchQuery = val;
                    });
                  },
                  decoration: const InputDecoration(
                    hintText: 'Buscar quiz, IES ou turma...',
                    prefixIcon: Icon(Icons.search, color: AppTheme.textSecondary),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                decoration: BoxDecoration(
                  color: AppTheme.surface,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.white.withOpacity(0.08)),
                ),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<String>(
                    value: _sortCriteria,
                    dropdownColor: AppTheme.surface,
                    icon: const Icon(Icons.sort, color: AppTheme.primary),
                    items: const [
                      DropdownMenuItem(value: 'date', child: Text('Mais Recentes')),
                      DropdownMenuItem(value: 'name', child: Text('Nome (A-Z)')),
                      DropdownMenuItem(value: 'corrected', child: Text('Corrigidas')),
                    ],
                    onChanged: (val) {
                      if (val != null) {
                        setState(() {
                          _sortCriteria = val;
                        });
                      }
                    },
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          // Quizzes List
          Expanded(
            child: sortedQuizzes.isEmpty
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.quiz_outlined, size: 64, color: AppTheme.textSecondary.withOpacity(0.5)),
                        const SizedBox(height: 16),
                        const Text(
                          'Nenhum quiz encontrado',
                          style: TextStyle(color: AppTheme.textSecondary, fontSize: 16),
                        ),
                      ],
                    ),
                  )
                : ListView.builder(
                    itemCount: sortedQuizzes.length,
                    itemBuilder: (context, index) {
                      final quiz = sortedQuizzes[index];
                      final institution = _appState.institutions.firstWhere(
                        (i) => i.id == quiz.institutionId,
                        orElse: () => Institution(id: '', name: 'Instituição Desconhecida'),
                      );
                      final classObj = _appState.classes.firstWhere(
                        (c) => c.id == quiz.classId,
                        orElse: () => Class(id: '', name: 'Turma Desconhecida', institutionId: ''),
                      );

                      return Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        decoration: BoxDecoration(
                          color: AppTheme.cardBg,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: Colors.white.withOpacity(0.05)),
                        ),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(16),
                          child: InkWell(
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (context) => QuizDetailScreen(quizId: quiz.id),
                                ),
                              );
                            },
                            child: Padding(
                              padding: const EdgeInsets.all(16.0),
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
                                            fontSize: 18,
                                            fontWeight: FontWeight.bold,
                                            color: AppTheme.textPrimary,
                                          ),
                                        ),
                                      ),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                        decoration: BoxDecoration(
                                          color: AppTheme.primary.withOpacity(0.15),
                                          borderRadius: BorderRadius.circular(8),
                                        ),
                                        child: Text(
                                          quiz.gabaritoTemplate,
                                          style: const TextStyle(
                                            color: AppTheme.secondary,
                                            fontSize: 12,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 12),
                                  Row(
                                    children: [
                                      const Icon(Icons.business, size: 16, color: AppTheme.textSecondary),
                                      const SizedBox(width: 6),
                                      Expanded(
                                        child: Text(
                                          institution.name,
                                          style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 6),
                                  Row(
                                    children: [
                                      const Icon(Icons.class_outlined, size: 16, color: AppTheme.textSecondary),
                                      const SizedBox(width: 6),
                                      Expanded(
                                        child: Text(
                                          classObj.name,
                                          style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const Divider(height: 24, color: Colors.white10),
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Text(
                                        'Corrigidas: ${quiz.correctedExamsCount}',
                                        style: TextStyle(
                                          color: quiz.correctedExamsCount > 0 ? AppTheme.success : AppTheme.textSecondary,
                                          fontSize: 13,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                      Text(
                                        'Data: ${DateFormat('dd/MM/yyyy').format(quiz.date)}',
                                        style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    ),
    floatingActionButton: FloatingActionButton(
      onPressed: () => _showCreateQuizSheet(context),
      backgroundColor: Colors.transparent,
      elevation: 0,
      child: Container(
        width: 56,
        height: 56,
        decoration: const BoxDecoration(
          shape: BoxShape.circle,
          gradient: AppTheme.primaryGradient,
        ),
        child: const Icon(Icons.add, color: Colors.white, size: 28),
      ),
    ),
  );
}

  void _showCreateQuizSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return const CreateQuizSheet();
      },
    );
  }
}

class CreateQuizSheet extends StatefulWidget {
  const CreateQuizSheet({Key? key}) : super(key: key);

  @override
  State<CreateQuizSheet> createState() => _CreateQuizSheetState();
}

class _CreateQuizSheetState extends State<CreateQuizSheet> {
  final _formKey = GlobalKey<FormState>();
  final AppState _appState = AppState();

  String _name = '';
  String _selectedTemplate = 'Gabarito 10 Questões';
  DateTime _selectedDate = DateTime.now();
  String? _selectedInstitutionId;
  String? _selectedClassId;

  final List<String> _templates = [
    'Gabarito 10 Questões',
    'Gabarito 20 Questões',
    'Gabarito 50 Questões',
  ];

  @override
  void initState() {
    super.initState();
    if (_appState.institutions.isNotEmpty) {
      _selectedInstitutionId = _appState.institutions.first.id;
      _updateClassesForInstitution(_selectedInstitutionId!);
    }
  }

  void _updateClassesForInstitution(String instId) {
    final filteredClasses = _appState.classes.where((c) => c.institutionId == instId).toList();
    if (filteredClasses.isNotEmpty) {
      _selectedClassId = filteredClasses.first.id;
    } else {
      _selectedClassId = null;
    }
  }

  Future<void> _selectDate(BuildContext context) async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime(2020),
      lastDate: DateTime(2030),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.dark(
              primary: AppTheme.primary,
              onPrimary: Colors.white,
              surface: AppTheme.surface,
              onSurface: AppTheme.textPrimary,
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null && picked != _selectedDate) {
      setState(() {
        _selectedDate = picked;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final filteredClasses = _appState.classes
        .where((c) => c.institutionId == _selectedInstitutionId)
        .toList();

    return Container(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
        left: 20,
        right: 20,
        top: 20,
      ),
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(24),
          topRight: Radius.circular(24),
        ),
      ),
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
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
              const Text(
                'Criar Novo Quiz',
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
              ),
              const SizedBox(height: 20),
              // Quiz Name
              TextFormField(
                decoration: const InputDecoration(
                  labelText: 'Nome do Quiz',
                  hintText: 'Ex: Prova Semestral de Redes',
                ),
                style: const TextStyle(color: AppTheme.textPrimary),
                validator: (val) {
                  if (val == null || val.isEmpty) return 'Digite o nome do quiz';
                  return null;
                },
                onSaved: (val) => _name = val ?? '',
              ),
              const SizedBox(height: 16),
              // Select Gabarito Template
              DropdownButtonFormField<String>(
                value: _selectedTemplate,
                decoration: const InputDecoration(labelText: 'Gabarito Utilizado'),
                dropdownColor: AppTheme.surface,
                items: _templates.map((t) {
                  return DropdownMenuItem(value: t, child: Text(t));
                }).toList(),
                onChanged: (val) {
                  if (val != null) {
                    setState(() {
                      _selectedTemplate = val;
                    });
                  }
                },
              ),
              const SizedBox(height: 16),
              // Date picker field
              InkWell(
                onTap: () => _selectDate(context),
                child: InputDecorator(
                  decoration: const InputDecoration(
                    labelText: 'Data da Avaliação',
                    suffixIcon: Icon(Icons.calendar_today, color: AppTheme.primary),
                  ),
                  child: Text(
                    DateFormat('dd/MM/yyyy').format(_selectedDate),
                    style: const TextStyle(color: AppTheme.textPrimary, fontSize: 16),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              // Institution Selection
              DropdownButtonFormField<String>(
                value: _selectedInstitutionId,
                decoration: const InputDecoration(labelText: 'Instituição (IES)'),
                dropdownColor: AppTheme.surface,
                items: _appState.institutions.map((i) {
                  return DropdownMenuItem(value: i.id, child: Text(i.name));
                }).toList(),
                onChanged: (val) {
                  if (val != null) {
                    setState(() {
                      _selectedInstitutionId = val;
                      _updateClassesForInstitution(val);
                    });
                  }
                },
              ),
              const SizedBox(height: 16),
              // Class Selection (filtered by Institution)
              DropdownButtonFormField<String>(
                value: _selectedClassId,
                decoration: const InputDecoration(labelText: 'Turma Vínculo'),
                dropdownColor: AppTheme.surface,
                items: filteredClasses.map((c) {
                  return DropdownMenuItem(value: c.id, child: Text(c.name));
                }).toList(),
                onChanged: (val) {
                  if (val != null) {
                    setState(() {
                      _selectedClassId = val;
                    });
                  }
                },
                validator: (val) {
                  if (val == null) return 'Selecione uma turma';
                  return null;
                },
              ),
              const SizedBox(height: 24),
              // Submit button
              SizedBox(
                width: double.infinity,
                height: 50,
                child: Container(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(12),
                    gradient: AppTheme.primaryGradient,
                  ),
                  child: ElevatedButton(
                    onPressed: _submitForm,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.transparent,
                      shadowColor: Colors.transparent,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text(
                      'Criar Quiz',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _submitForm() {
    if (_formKey.currentState!.validate()) {
      _formKey.currentState!.save();
      
      // Determine questions count from template
      int questionsCount = 10;
      if (_selectedTemplate.contains('20')) {
        questionsCount = 20;
      } else if (_selectedTemplate.contains('50')) {
        questionsCount = 50;
      }

      // Default question keys (A, each worth 1.0 point, redistribution off)
      final keys = List.generate(questionsCount, (index) {
        final options = ['A', 'B', 'C', 'D', 'E'];
        return QuestionKey(
          questionNumber: index + 1,
          correctOption: options[index % 5],
          points: 10.0 / questionsCount, // distributes 10 points evenly
        );
      });

      final newQuiz = Quiz(
        id: 'q_${DateTime.now().millisecondsSinceEpoch}',
        name: _name,
        gabaritoTemplate: _selectedTemplate,
        date: _selectedDate,
        institutionId: _selectedInstitutionId!,
        classId: _selectedClassId!,
        questionsCount: questionsCount,
        totalPoints: 10.0,
        correctedExamsCount: 0,
        questionKeys: keys,
      );

      _appState.createQuiz(newQuiz);
      Navigator.pop(context);
    }
  }
}
