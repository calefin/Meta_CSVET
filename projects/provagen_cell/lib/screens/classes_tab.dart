import 'package:flutter/material.dart';
import '../services/app_state.dart';
import '../theme/app_theme.dart';
import 'students_tab.dart'; // Navigating or filtering

class ClassesTab extends StatefulWidget {
  const ClassesTab({Key? key}) : super(key: key);

  @override
  State<ClassesTab> createState() => _ClassesTabState();
}

class _ClassesTabState extends State<ClassesTab> {
  final AppState _appState = AppState();

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

  @override
  Widget build(BuildContext context) {
    if (_appState.institutions.isEmpty) {
      return const Center(
        child: Text('Nenhuma instituição cadastrada.', style: TextStyle(color: AppTheme.textSecondary)),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16.0),
      itemCount: _appState.institutions.length,
      itemBuilder: (context, index) {
        final institution = _appState.institutions[index];
        final institutionClasses = _appState.classes
            .where((c) => c.institutionId == institution.id)
            .toList();

        return Container(
          margin: const EdgeInsets.only(bottom: 16),
          decoration: BoxDecoration(
            color: AppTheme.cardBg,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: Colors.white.withOpacity(0.05)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Institution Header with premium gradient badge
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [
                      AppTheme.primary.withOpacity(0.15),
                      AppTheme.accent.withOpacity(0.05),
                    ],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: const BorderRadius.only(
                    topLeft: Radius.circular(20),
                    topRight: Radius.circular(20),
                  ),
                  border: Border(
                    bottom: BorderSide(color: Colors.white.withOpacity(0.05)),
                  ),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.business, color: AppTheme.secondary, size: 24),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        institution.name,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.textPrimary,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              // List of Classes
              if (institutionClasses.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(20.0),
                  child: Text(
                    'Nenhuma turma vinculada a esta instituição.',
                    style: TextStyle(color: AppTheme.textSecondary, fontStyle: FontStyle.italic),
                  ),
                )
              else
                ListView.separated(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: institutionClasses.length,
                  separatorBuilder: (context, i) => Divider(
                    height: 1,
                    color: Colors.white.withOpacity(0.03),
                    indent: 16,
                    endIndent: 16,
                  ),
                  itemBuilder: (context, cIdx) {
                    final classObj = institutionClasses[cIdx];
                    final studentCount = _appState.students
                        .where((s) => s.classId == classObj.id)
                        .length;

                    return Material(
                      color: Colors.transparent,
                      child: ListTile(
                        contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                        title: Text(
                          classObj.name,
                          style: const TextStyle(
                            color: AppTheme.textPrimary,
                            fontWeight: FontWeight.w600,
                            fontSize: 15,
                          ),
                        ),
                        subtitle: Text(
                          '$studentCount alunos cadastrados',
                          style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
                        ),
                        trailing: const Icon(Icons.chevron_right, color: AppTheme.textSecondary),
                        onTap: () {
                          // Open student list filtered by this class
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (context) => Scaffold(
                                appBar: AppBar(
                                  title: Text(classObj.name),
                                ),
                                body: StudentsTab(filterClassId: classObj.id),
                              ),
                            ),
                          );
                        },
                      ),
                    );
                  },
                ),
            ],
          ),
        );
      },
    );
  }
}
