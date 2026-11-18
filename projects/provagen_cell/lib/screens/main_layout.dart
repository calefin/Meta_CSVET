import 'package:flutter/material.dart';
import '../services/app_state.dart';
import '../services/translation_service.dart';
import 'quizzes_tab.dart';
import 'classes_tab.dart';
import 'students_tab.dart';
import 'account_tab.dart';

class MainLayout extends StatefulWidget {
  const MainLayout({Key? key}) : super(key: key);

  @override
  State<MainLayout> createState() => _MainLayoutState();
}

class _MainLayoutState extends State<MainLayout> {
  int _currentIndex = 0;
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
    if (mounted) {
      setState(() {});
    }
  }

  final List<Widget> _tabs = [
    const QuizzesTab(),
    const ClassesTab(),
    const StudentsTab(),
    const AccountTab(),
  ];

  List<String> get _titles => [
    AppTranslations.translate('tab_quizzes'),
    AppTranslations.translate('classes_title'),
    AppTranslations.translate('tab_students'),
    AppTranslations.translate('account_title'),
  ];

  Widget _buildConnectionIndicator(BuildContext context) {
    final isOnline = _appState.isBackendOnline;
    final isChecking = _appState.isCheckingConnection;

    return Padding(
      padding: const EdgeInsets.only(right: 8.0),
      child: IconButton(
        icon: isChecking
            ? const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  valueColor: AlwaysStoppedAnimation<Color>(Colors.amber),
                ),
              )
            : Icon(
                isOnline ? Icons.cloud_done : Icons.cloud_off,
                color: isOnline ? Colors.greenAccent : Colors.amberAccent,
              ),
        tooltip: isOnline
            ? AppTranslations.translate('backend_online')
            : AppTranslations.translate('backend_offline'),
        onPressed: () => _showConnectionStatusDialog(context),
      ),
    );
  }

  void _showConnectionStatusDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setStateInDialog) {
            final isOnline = _appState.isBackendOnline;
            final isChecking = _appState.isCheckingConnection;
            
            return AlertDialog(
              backgroundColor: const Color(0xFF1E293B),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: Row(
                children: [
                  Icon(
                    isOnline ? Icons.cloud_done : Icons.cloud_off,
                    color: isOnline ? Colors.greenAccent : Colors.amberAccent,
                  ),
                  const SizedBox(width: 10),
                  Text(
                    AppTranslations.translate('status_backend'),
                    style: const TextStyle(color: Colors.white),
                  ),
                ],
              ),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'URL: ${_appState.settings.backendUrl}',
                    style: const TextStyle(color: Colors.white70, fontSize: 13, fontFamily: 'monospace'),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    isOnline
                        ? AppTranslations.translate('backend_online')
                        : AppTranslations.translate('backend_offline'),
                    style: TextStyle(
                      color: isOnline ? Colors.greenAccent : Colors.amberAccent,
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Se offline, as correções OMR serão simuladas localmente com dados de demonstração.',
                    style: TextStyle(color: Colors.white54, fontSize: 12),
                  ),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: Text(
                    AppTranslations.translate('cancel'),
                    style: const TextStyle(color: Colors.white54),
                  ),
                ),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF3B82F6),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  onPressed: isChecking
                      ? null
                      : () async {
                          await _appState.checkBackendConnection();
                          if (context.mounted) {
                            setStateInDialog(() {});
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(
                                  _appState.isBackendOnline
                                      ? AppTranslations.translate('conn_success')
                                      : AppTranslations.translate('conn_failed'),
                                ),
                                backgroundColor: _appState.isBackendOnline
                                    ? Colors.green
                                    : Colors.redAccent,
                                duration: const Duration(seconds: 2),
                              ),
                            );
                          }
                        },
                  child: isChecking
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                          ),
                        )
                      : Text(
                          AppTranslations.translate('btn_test_conn'),
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                        ),
                ),
              ],
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_titles[_currentIndex]),
        actions: [
          _buildConnectionIndicator(context),
        ],
        flexibleSpace: Container(
          decoration: const BoxDecoration(
            border: Border(
              bottom: BorderSide(
                color: Colors.white10,
                width: 1,
              ),
            ),
          ),
        ),
      ),
      body: IndexedStack(
        index: _currentIndex,
        children: _tabs,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          border: Border(
            top: BorderSide(
              color: Colors.white.withOpacity(0.08),
              width: 1,
            ),
          ),
        ),
        child: BottomNavigationBar(
          currentIndex: _currentIndex,
          onTap: (index) {
            setState(() {
              _currentIndex = index;
            });
          },
          items: [
            BottomNavigationBarItem(
              icon: const Icon(Icons.quiz_outlined),
              activeIcon: const Icon(Icons.quiz),
              label: AppTranslations.translate('tab_quizzes'),
            ),
            BottomNavigationBarItem(
              icon: const Icon(Icons.school_outlined),
              activeIcon: const Icon(Icons.school),
              label: AppTranslations.translate('tab_classes'),
            ),
            BottomNavigationBarItem(
              icon: const Icon(Icons.people_alt_outlined),
              activeIcon: const Icon(Icons.people_alt),
              label: AppTranslations.translate('tab_students'),
            ),
            BottomNavigationBarItem(
              icon: const Icon(Icons.account_circle_outlined),
              activeIcon: const Icon(Icons.account_circle),
              label: AppTranslations.translate('tab_account'),
            ),
          ],
        ),
      ),
    );
  }
}
