import 'package:flutter/material.dart';
import 'screens/main_layout.dart';
import 'screens/login_screen.dart';
import 'services/app_state.dart';
import 'theme/app_theme.dart';

void main() async {
  // Ensure Flutter engine is initialized
  WidgetsFlutterBinding.ensureInitialized();
  
  // Initialize AppState (loads database caching and sets up initial mock data)
  final appState = AppState();
  await appState.initialize();

  runApp(const ProvaGenApp());
}

class ProvaGenApp extends StatelessWidget {
  const ProvaGenApp({super.key});

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: AppState(),
      builder: (context, child) {
        final appState = AppState();
        
        if (appState.isLoading) {
          return MaterialApp(
            debugShowCheckedModeBanner: false,
            theme: AppTheme.darkTheme,
            home: const Scaffold(
              body: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CircularProgressIndicator(color: AppTheme.primary),
                    SizedBox(height: 16),
                    Text(
                      'Inicializando ProvaGen...',
                      style: TextStyle(
                        color: AppTheme.textSecondary,
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        }

        return MaterialApp(
          title: 'ProvaGen Corrector',
          debugShowCheckedModeBanner: false,
          theme: AppTheme.darkTheme,
          home: appState.isLoggedIn ? const MainLayout() : const LoginScreen(),
        );
      },
    );
  }
}
