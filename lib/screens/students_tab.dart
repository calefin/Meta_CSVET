import 'package:flutter/material.dart';
import '../models/app_models.dart';
import '../services/app_state.dart';
import '../theme/app_theme.dart';

class StudentsTab extends StatefulWidget {
  final String? filterClassId;
  const StudentsTab({Key? key, this.filterClassId}) : super(key: key);

  @override
  State<StudentsTab> createState() => _StudentsTabState();
}

class _StudentsTabState extends State<StudentsTab> {
  final AppState _appState = AppState();
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

  List<Student> _getFilteredStudents() {
    return _appState.students.where((student) {
      // Filter by class if parameter is set
      if (widget.filterClassId != null && student.classId != widget.filterClassId) {
        return false;
      }
      
      // Filter by search query (name or registry)
      final nameMatches = student.name.toLowerCase().contains(_searchQuery.toLowerCase());
      final registryMatches = student.identifier.toLowerCase().contains(_searchQuery.toLowerCase());
      return nameMatches || registryMatches;
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final studentsList = _getFilteredStudents();

    return Padding(
      padding: const EdgeInsets.all(16.0),
      child: Column(
        children: [
          // Search box (shown only if not already within a small container, but always useful)
          TextField(
            onChanged: (val) {
              setState(() {
                _searchQuery = val;
              });
            },
            decoration: const InputDecoration(
              hintText: 'Filtrar alunos por nome ou RM...',
              prefixIcon: Icon(Icons.search, color: AppTheme.textSecondary),
            ),
          ),
          const SizedBox(height: 16),
          // Students List
          Expanded(
            child: studentsList.isEmpty
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.person_off_outlined, size: 64, color: AppTheme.textSecondary.withOpacity(0.5)),
                        const SizedBox(height: 16),
                        const Text(
                          'Nenhum aluno cadastrado.',
                          style: TextStyle(color: AppTheme.textSecondary, fontSize: 16),
                        ),
                      ],
                    ),
                  )
                : ListView.builder(
                    itemCount: studentsList.length,
                    itemBuilder: (context, index) {
                      final student = studentsList[index];
                      final classObj = _appState.classes.firstWhere(
                        (c) => c.id == student.classId,
                        orElse: () => Class(id: '', name: 'Sem Turma', institutionId: ''),
                      );
                      
                      return Card(
                        margin: const EdgeInsets.only(bottom: 12),
                        child: Material(
                          color: Colors.transparent,
                          child: ListTile(
                            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                            leading: CircleAvatar(
                              backgroundColor: AppTheme.primary.withOpacity(0.15),
                              child: Text(
                                student.name.substring(0, 1).toUpperCase(),
                                style: const TextStyle(color: AppTheme.secondary, fontWeight: FontWeight.bold),
                              ),
                            ),
                            title: Text(
                              student.name,
                              style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                            ),
                            subtitle: Text(
                              'RM: ${student.identifier} | ${classObj.name}',
                              style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
                            ),
                            trailing: const Icon(Icons.arrow_forward_ios, size: 16, color: AppTheme.textSecondary),
                            onTap: () => _showStudentDetails(context, student),
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

  void _showStudentDetails(BuildContext context, Student student) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return StudentDetailsSheet(studentId: student.id);
      },
    );
  }
}

class StudentDetailsSheet extends StatefulWidget {
  final String studentId;
  const StudentDetailsSheet({Key? key, required this.studentId}) : super(key: key);

  @override
  State<StudentDetailsSheet> createState() => _StudentDetailsSheetState();
}

class _StudentDetailsSheetState extends State<StudentDetailsSheet> {
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
    // Reload student from app state to get real-time edits
    final student = _appState.students.firstWhere(
      (s) => s.id == widget.studentId,
      orElse: () => Student(id: '', name: '', identifier: '', classId: '', quizGrades: {}),
    );

    if (student.id.isEmpty) {
      return const SizedBox.shrink();
    }

    final classObj = _appState.classes.firstWhere(
      (c) => c.id == student.classId,
      orElse: () => Class(id: '', name: 'Sem Turma', institutionId: ''),
    );

    final institution = _appState.institutions.firstWhere(
      (i) => i.id == classObj.institutionId,
      orElse: () => Institution(id: '', name: 'Instituição Desconhecida'),
    );

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(24),
          topRight: Radius.circular(24),
        ),
      ),
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
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Ficha do Aluno',
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
              ),
              IconButton(
                icon: const Icon(Icons.edit, color: AppTheme.secondary),
                onPressed: () {
                  Navigator.pop(context);
                  _showEditStudentDialog(context, student);
                },
              ),
            ],
          ),
          const SizedBox(height: 16),
          // Student details card
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppTheme.cardBg,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.white.withOpacity(0.05)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  student.name,
                  style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.white),
                ),
                const SizedBox(height: 8),
                Text('Registro (RM): ${student.identifier}', style: const TextStyle(color: AppTheme.textSecondary)),
                const SizedBox(height: 4),
                Text('Instituição: ${institution.name}', style: const TextStyle(color: AppTheme.textSecondary)),
                const SizedBox(height: 4),
                Text('Turma Atual: ${classObj.name}', style: const TextStyle(color: AppTheme.textSecondary)),
              ],
            ),
          ),
          const SizedBox(height: 20),
          const Text(
            'Notas Atribuídas',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
          ),
          const SizedBox(height: 10),
          // Grades list
          if (student.quizGrades.isEmpty)
            Container(
              padding: const EdgeInsets.symmetric(vertical: 24),
              width: double.infinity,
              alignment: Alignment.center,
              child: const Text(
                'Nenhuma nota lançada para este aluno.',
                style: TextStyle(color: AppTheme.textSecondary, fontStyle: FontStyle.italic),
              ),
            )
          else
            ListView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: student.quizGrades.length,
              itemBuilder: (context, index) {
                final quizId = student.quizGrades.keys.elementAt(index);
                final grade = student.quizGrades[quizId]!;
                
                final quiz = _appState.quizzes.firstWhere(
                  (q) => q.id == quizId,
                  orElse: () => Quiz(id: quizId, name: 'Quiz Antigo / Arquivado', gabaritoTemplate: '', date: DateTime.now(), institutionId: '', classId: '', questionsCount: 0, totalPoints: 10.0, correctedExamsCount: 0, questionKeys: []),
                );

                return Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  decoration: BoxDecoration(
                    color: AppTheme.surface,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.white.withOpacity(0.04)),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          quiz.name,
                          style: const TextStyle(color: AppTheme.textPrimary, fontWeight: FontWeight.w500),
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: grade >= 6.0
                              ? AppTheme.success.withOpacity(0.15)
                              : AppTheme.error.withOpacity(0.15),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          grade.toStringAsFixed(1),
                          style: TextStyle(
                            color: grade >= 6.0 ? AppTheme.success : AppTheme.error,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  void _showEditStudentDialog(BuildContext context, Student student) {
    showDialog(
      context: context,
      builder: (context) {
        return EditStudentDialog(student: student);
      },
    );
  }
}

class EditStudentDialog extends StatefulWidget {
  final Student student;
  const EditStudentDialog({Key? key, required this.student}) : super(key: key);

  @override
  State<EditStudentDialog> createState() => _EditStudentDialogState();
}

class _EditStudentDialogState extends State<EditStudentDialog> {
  final _formKey = GlobalKey<FormState>();
  final AppState _appState = AppState();
  
  late String _name;
  late String _identifier;
  late String _classId;

  @override
  void initState() {
    super.initState();
    _name = widget.student.name;
    _identifier = widget.student.identifier;
    _classId = widget.student.classId;
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Editar Aluno'),
      backgroundColor: AppTheme.surface,
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                initialValue: _name,
                decoration: const InputDecoration(labelText: 'Nome do Aluno'),
                style: const TextStyle(color: AppTheme.textPrimary),
                validator: (val) => val == null || val.isEmpty ? 'Digite o nome' : null,
                onSaved: (val) => _name = val ?? '',
              ),
              const SizedBox(height: 12),
              TextFormField(
                initialValue: _identifier,
                decoration: const InputDecoration(labelText: 'Registro (RM)'),
                style: const TextStyle(color: AppTheme.textPrimary),
                validator: (val) => val == null || val.isEmpty ? 'Digite o RM' : null,
                onSaved: (val) => _identifier = val ?? '',
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                value: _classId,
                decoration: const InputDecoration(labelText: 'Turma Atribuída'),
                dropdownColor: AppTheme.surface,
                items: _appState.classes.map((c) {
                  final inst = _appState.institutions.firstWhere((i) => i.id == c.institutionId, orElse: () => Institution(id: '', name: ''));
                  return DropdownMenuItem(
                    value: c.id,
                    child: Text('${c.name} (${inst.name})'),
                  );
                }).toList(),
                onChanged: (val) {
                  if (val != null) {
                    setState(() {
                      _classId = val;
                    });
                  }
                },
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancelar', style: TextStyle(color: AppTheme.textSecondary)),
        ),
        ElevatedButton(
          onPressed: _saveEdits,
          style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
          child: const Text('Salvar', style: TextStyle(color: Colors.white)),
        ),
      ],
    );
  }

  void _saveEdits() {
    if (_formKey.currentState!.validate()) {
      _formKey.currentState!.save();
      
      final updatedStudent = widget.student.copyWith(
        name: _name,
        identifier: _identifier,
        classId: _classId,
      );

      _appState.updateStudent(updatedStudent);
      Navigator.pop(context);
      
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Dados do aluno atualizados com sucesso!'),
          backgroundColor: AppTheme.success,
        ),
      );
    }
  }
}
