import 'package:flutter/material.dart';
import '../models/app_models.dart';
import 'db_service.dart';
import 'api_service.dart';

class AppState extends ChangeNotifier {
  static final AppState _instance = AppState._internal();
  factory AppState() => _instance;
  AppState._internal();

  final _dbService = DbService();
  final _apiService = ApiService();

  List<Quiz> quizzes = [];
  List<Class> classes = [];
  List<Student> students = [];
  List<Institution> institutions = [];
  AppSettings settings = AppSettings();
  
  bool isLoading = false;
  bool isSyncing = false;
  String userEmail = 'atilio@prof-aid.com';
  String subscriptionStatus = '';
  String? userToken;
  DateTime? lastSyncTime;
  bool hasUnsynced = false;

  bool isBackendOnline = false;
  bool isCheckingConnection = false;

  bool get isLoggedIn => userToken != null;

  Future<void> initialize() async {
    isLoading = true;
    notifyListeners();

    await _dbService.init();
    await refreshState();
    // Do not block initial load for the connection ping, run it asynchronously
    checkBackendConnection();

    isLoading = false;
    notifyListeners();
  }

  Future<void> checkBackendConnection() async {
    isCheckingConnection = true;
    notifyListeners();
    try {
      isBackendOnline = await _apiService.checkBackendConnection();
    } catch (_) {
      isBackendOnline = false;
    } finally {
      isCheckingConnection = false;
      notifyListeners();
    }
  }

  Future<void> refreshState() async {
    quizzes = await _dbService.getQuizzes();
    classes = await _dbService.getClasses();
    students = await _dbService.getStudents();
    institutions = await _dbService.getInstitutions();
    settings = await _dbService.getSettings();
    _apiService.setBackendUrl(settings.backendUrl);
    userEmail = await _dbService.getUserEmail();
    subscriptionStatus = await _dbService.getSubscriptionStatus();
    userToken = await _dbService.getUserToken();
    lastSyncTime = await _dbService.getLastSyncTime();
    hasUnsynced = await _dbService.hasUnsyncedData();
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    isLoading = true;
    notifyListeners();
    try {
      final res = await _apiService.login(email, password);
      userToken = res['token'];
      userEmail = res['email'];
      final Map<String, dynamic> sub = res['subscription'] ?? {};
      subscriptionStatus = sub['plan'] ?? 'Premium Ativo (Válido até 31/12/2026)';
      
      // Persist in local DB
      await _dbService.setUserToken(userToken);
      await _dbService.setUserEmail(userEmail);
      await _dbService.setSubscriptionStatus(subscriptionStatus);
      await refreshState();
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    isLoading = true;
    notifyListeners();
    userToken = null;
    await _dbService.setUserToken(null);
    await refreshState();
    isLoading = false;
    notifyListeners();
  }

  Future<void> validateUserSubscription() async {
    if (userToken == null) return;
    isSyncing = true;
    notifyListeners();
    try {
      final res = await _apiService.validateSubscription(userToken!);
      final status = res['status'] ?? 'active';
      final plan = res['plan'] ?? 'Premium';
      
      if (status == 'expired' || status == 'inactive') {
        subscriptionStatus = 'Assinatura Inativa / Expirada';
      } else {
        subscriptionStatus = plan;
      }
      await _dbService.setSubscriptionStatus(subscriptionStatus);
      await refreshState();
    } catch (e) {
      // In case of error (e.g. offline) validate local mock
      if (userToken!.startsWith('mock_token_')) {
        if (userEmail == 'expired@prof-aid.com') {
          subscriptionStatus = 'Assinatura Gratuita (Expirada)';
        } else {
          subscriptionStatus = 'Premium Ativo (Validado Offline)';
        }
        await _dbService.setSubscriptionStatus(subscriptionStatus);
      }
      rethrow;
    } finally {
      isSyncing = false;
      notifyListeners();
    }
  }

  // --- Quizzes ---
  Future<void> createQuiz(Quiz quiz) async {
    await _dbService.saveQuiz(quiz);
    await refreshState();
  }

  Future<void> updateQuiz(Quiz quiz) async {
    await _dbService.saveQuiz(quiz);
    await recalculateQuizPapers(quiz.id);
    await refreshState();
  }

  Future<void> recalculateQuizPapers(String quizId) async {
    // Fetch latest states directly
    final currentQuizzes = await _dbService.getQuizzes();
    final quiz = currentQuizzes.firstWhere((q) => q.id == quizId);
    final papers = await _dbService.getCorrectedPapers(quizId);
    if (papers.isEmpty) return;

    final updatedPapers = <CorrectedPaper>[];
    
    // We calculate extra points per question from cancelled questions
    double cancelledPointsToDistribute = 0.0;
    int activeQuestionsCount = 0;

    for (var key in quiz.questionKeys) {
      if (key.isCancelled) {
        if (key.redistributePoints) {
          cancelledPointsToDistribute += key.points;
        }
      } else {
        activeQuestionsCount++;
      }
    }

    final double extraPointsPerQuestion = activeQuestionsCount > 0 
        ? cancelledPointsToDistribute / activeQuestionsCount 
        : 0.0;

    final currentStudents = await _dbService.getStudents();

    for (var paper in papers) {
      double newGrade = 0.0;
      int correctCount = 0;
      int wrongCount = 0;

      for (var key in quiz.questionKeys) {
        final studentAns = paper.answers[key.questionNumber] ?? '';
        
        if (key.isCancelled) {
          correctCount++;
          if (!key.redistributePoints) {
            newGrade += key.points;
          }
        } else {
          if (studentAns == key.correctOption) {
            correctCount++;
            newGrade += key.points + extraPointsPerQuestion;
          } else {
            wrongCount++;
          }
        }
      }

      newGrade = double.parse(newGrade.toStringAsFixed(2));
      if (newGrade > quiz.totalPoints) newGrade = quiz.totalPoints;

      final updatedPaper = paper.copyWith(
        grade: newGrade,
        correctCount: correctCount,
        wrongCount: wrongCount,
      );
      updatedPapers.add(updatedPaper);

      // Also update the student's grade record for this quiz
      final studentIndex = currentStudents.indexWhere((s) => s.id == paper.studentId);
      if (studentIndex != -1) {
        final student = currentStudents[studentIndex];
        final updatedGrades = Map<String, double>.from(student.quizGrades);
        updatedGrades[quizId] = newGrade;
        await _dbService.saveStudent(student.copyWith(quizGrades: updatedGrades));
      }
    }

    await _dbService.saveCorrectedPapers(quizId, updatedPapers);
  }

  // --- Students ---
  Future<void> updateStudent(Student student) async {
    await _dbService.saveStudent(student);
    await refreshState();
  }

  // --- Corrected Papers ---
  Future<List<CorrectedPaper>> getCorrectedPapers(String quizId) async {
    return await _dbService.getCorrectedPapers(quizId);
  }

  Future<void> addCorrectedPaper(String quizId, CorrectedPaper paper) async {
    await _dbService.addCorrectedPaper(quizId, paper);
    await refreshState();
  }

  Future<void> updateCorrectedPapersList(String quizId, List<CorrectedPaper> papers) async {
    await _dbService.saveCorrectedPapers(quizId, papers);
    await refreshState();
  }

  // --- Settings ---
  Future<void> updateSettings(AppSettings newSettings) async {
    settings = newSettings;
    await _dbService.saveSettings(newSettings);
    notifyListeners();
  }

  // --- Key Editing ---
  Future<void> updateQuestionKey(String quizId, int questionNumber, String correctOption, double points, bool isCancelled, bool redistribute) async {
    final quizIndex = quizzes.indexWhere((q) => q.id == quizId);
    if (quizIndex != -1) {
      final quiz = quizzes[quizIndex];
      final keys = List<QuestionKey>.from(quiz.questionKeys);
      final keyIndex = keys.indexWhere((k) => k.questionNumber == questionNumber);
      if (keyIndex != -1) {
        keys[keyIndex] = QuestionKey(
          questionNumber: questionNumber,
          correctOption: correctOption,
          points: points,
          isCancelled: isCancelled,
          redistributePoints: redistribute,
        );
      }
      
      // Calculate new total points
      double total = 0.0;
      for (var k in keys) {
        total += k.points;
      }
      
      final updatedQuiz = quiz.copyWith(questionKeys: keys, totalPoints: total);
      await _dbService.saveQuiz(updatedQuiz);
      await recalculateQuizPapers(quizId);
      await refreshState();
    }
  }

  Future<void> updateQuestionKeysGlobally(String quizId, double globalPoints) async {
    final quizIndex = quizzes.indexWhere((q) => q.id == quizId);
    if (quizIndex != -1) {
      final quiz = quizzes[quizIndex];
      final keys = quiz.questionKeys.map((k) {
        return QuestionKey(
          questionNumber: k.questionNumber,
          correctOption: k.correctOption,
          points: globalPoints,
          isCancelled: k.isCancelled,
          redistributePoints: k.redistributePoints,
        );
      }).toList();

      final total = globalPoints * keys.length;
      final updatedQuiz = quiz.copyWith(questionKeys: keys, totalPoints: total);
      await _dbService.saveQuiz(updatedQuiz);
      await recalculateQuizPapers(quizId);
      await refreshState();
    }
  }

  // --- Syncing ---
  Future<void> sync() async {
    isSyncing = true;
    notifyListeners();
    
    try {
      final token = await _dbService.getUserToken();
      if (token == null) {
        // User not logged in, sync skipped
        isSyncing = false;
        notifyListeners();
        return;
      }

      // 1. Sync Up: Upload local corrected papers
      final quizzesList = await _dbService.getQuizzes();
      bool uploadError = false;

      for (var quiz in quizzesList) {
        final papers = await _dbService.getCorrectedPapers(quiz.id);
        if (papers.isNotEmpty) {
          // Parse examId (e.g., 'q1' -> 1)
          final examId = int.tryParse(quiz.id) ?? 
                         int.tryParse(quiz.id.replaceAll(RegExp(r'\D'), '')) ?? 
                         1;

          final success = await _apiService.syncPapers(
            examId: examId,
            papers: papers,
            token: token,
          );

          if (!success) {
            uploadError = true;
          }
        }
      }

      // 2. Sync Down: Download real data from VPS database
      final syncData = await _apiService.fetchSyncData(token);
      if (syncData != null) {
        // Save Institutions
        if (syncData['institutions'] != null) {
          final List instList = syncData['institutions'];
          final List<Institution> loadedInstitutions = instList.map((e) => Institution.fromJson(e)).toList();
          await _dbService.saveInstitutions(loadedInstitutions);
        }

        // Save Classes
        if (syncData['classes'] != null) {
          final List classList = syncData['classes'];
          final List<Class> loadedClasses = classList.map((e) => Class.fromJson(e)).toList();
          await _dbService.saveClasses(loadedClasses);
        }

        // Save Students
        if (syncData['students'] != null) {
          final List studentList = syncData['students'];
          final List<Student> loadedStudents = studentList.map((e) => Student.fromJson(e)).toList();
          
          // Re-populate their locally corrected grades if we have them
          for (var i = 0; i < loadedStudents.length; i++) {
            final student = loadedStudents[i];
            final Map<String, double> updatedGrades = {};
            
            for (var quiz in quizzesList) {
              final papers = await _dbService.getCorrectedPapers(quiz.id);
              final matches = papers.where((p) => p.studentId == student.id);
              if (matches.isNotEmpty) {
                updatedGrades[quiz.id] = matches.first.grade;
              }
            }
            loadedStudents[i] = student.copyWith(quizGrades: updatedGrades);
          }
          await _dbService.saveStudents(loadedStudents);
        }

        // Save Quizzes (Exams)
        if (syncData['exams'] != null) {
          final List examList = syncData['exams'];
          final List<Quiz> loadedQuizzes = examList.map((e) => Quiz.fromJson(e)).toList();
          
          for (var i = 0; i < loadedQuizzes.length; i++) {
            final q = loadedQuizzes[i];
            final localPapers = await _dbService.getCorrectedPapers(q.id);
            loadedQuizzes[i] = q.copyWith(correctedExamsCount: localPapers.length);
          }
          await _dbService.saveQuizzes(loadedQuizzes);
        }
      }

      if (!uploadError) {
        await _dbService.setUnsynced(false);
        await _dbService.setLastSyncTime(DateTime.now());
      }
    } catch (e) {
      print('Error during sync: $e');
    }

    await refreshState();
    
    isSyncing = false;
    notifyListeners();
  }

  Future<void> resetAll() async {
    isLoading = true;
    notifyListeners();
    await _dbService.resetAll();
    await refreshState();
    isLoading = false;
    notifyListeners();
  }

  // --- Scanner Process API ---
  Future<CorrectedPaper> scanAndCorrect({
    required String quizId,
    required String imagePath,
  }) async {
    final quiz = quizzes.firstWhere((q) => q.id == quizId);
    
    // Call the network or simulated backend api
    final paper = await _apiService.correctExamSheet(
      quizId: quizId,
      imagePath: imagePath,
      keys: quiz.questionKeys,
      settings: settings,
    );

    // Save locally
    await addCorrectedPaper(quizId, paper);
    return paper;
  }
}
