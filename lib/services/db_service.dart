import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/app_models.dart';

class DbService {
  static final DbService _instance = DbService._internal();
  factory DbService() => _instance;
  DbService._internal();

  SharedPreferences? _prefs;

  Future<void> init() async {
    _prefs = await SharedPreferences.getInstance();
    if (!_prefs!.containsKey('initialized')) {
      await _prepopulateData();
    }
  }

  Future<void> _prepopulateData() async {
    // Institutions
    final institutions = [
      Institution(id: '1', name: 'UNIP - Universidade Paulista'),
      Institution(id: '2', name: 'USP - Universidade de São Paulo'),
    ];
    await saveInstitutions(institutions);

    // Classes
    final classes = [
      Class(id: '101', name: 'Engenharia de Software - 5º Semestre', institutionId: '1'),
      Class(id: '102', name: 'Algoritmos e Estruturas de Dados - 2º Semestre', institutionId: '1'),
      Class(id: '201', name: 'Cálculo I - Turma A', institutionId: '2'),
      Class(id: '202', name: 'Introdução à Computação', institutionId: '2'),
    ];
    await saveClasses(classes);

    // Students
    final students = [
      Student(id: 's1', name: 'Carlos Eduardo da Silva', identifier: '20261011', classId: '101', quizGrades: {'q1': 8.0}),
      Student(id: 's2', name: 'Mariana Souza Oliveira', identifier: '20261012', classId: '101', quizGrades: {'q1': 9.0}),
      Student(id: 's3', name: 'Felipe Almeida Santos', identifier: '20261013', classId: '101', quizGrades: {}),
      Student(id: 's4', name: 'Amanda Pereira Lima', identifier: '20261014', classId: '101', quizGrades: {}),
      Student(id: 's5', name: 'Rodrigo Costa Gomes', identifier: '20262011', classId: '201', quizGrades: {}),
      Student(id: 's6', name: 'Beatriz Mello Rezende', identifier: '20262012', classId: '201', quizGrades: {}),
    ];
    await saveStudents(students);

    // Quizzes
    final q1Keys = List.generate(10, (index) {
      final options = ['A', 'B', 'C', 'D', 'E'];
      return QuestionKey(
        questionNumber: index + 1,
        correctOption: options[index % 5],
        points: 1.0,
      );
    });

    final q2Keys = List.generate(20, (index) {
      final options = ['E', 'D', 'C', 'B', 'A'];
      return QuestionKey(
        questionNumber: index + 1,
        correctOption: options[index % 5],
        points: 0.5,
      );
    });

    final quizzes = [
      Quiz(
        id: 'q1',
        name: 'P1 - Banco de Dados',
        gabaritoTemplate: 'Gabarito 10 Questões',
        date: DateTime.now().subtract(const Duration(days: 3)),
        institutionId: '1',
        classId: '101',
        questionsCount: 10,
        totalPoints: 10.0,
        correctedExamsCount: 2,
        questionKeys: q1Keys,
      ),
      Quiz(
        id: 'q2',
        name: 'P2 - Engenharia de Requisitos',
        gabaritoTemplate: 'Gabarito 20 Questões',
        date: DateTime.now().add(const Duration(days: 4)),
        institutionId: '1',
        classId: '101',
        questionsCount: 20,
        totalPoints: 10.0,
        correctedExamsCount: 0,
        questionKeys: q2Keys,
      ),
    ];
    await saveQuizzes(quizzes);

    // Corrected Papers (for Q1)
    final paper1 = CorrectedPaper(
      id: 'p1',
      quizId: 'q1',
      studentId: 's1',
      studentName: 'Carlos Eduardo da Silva',
      grade: 8.0,
      correctCount: 8,
      wrongCount: 2,
      answers: {1: 'A', 2: 'B', 3: 'C', 4: 'D', 5: 'E', 6: 'A', 7: 'B', 8: 'C', 9: 'A', 10: 'B'}, // correct was A,B,C,D,E,A,B,C,D,E
      scannedAt: DateTime.now().subtract(const Duration(days: 2)),
    );

    final paper2 = CorrectedPaper(
      id: 'p2',
      quizId: 'q1',
      studentId: 's2',
      studentName: 'Mariana Souza Oliveira',
      grade: 9.0,
      correctCount: 9,
      wrongCount: 1,
      answers: {1: 'A', 2: 'B', 3: 'C', 4: 'D', 5: 'E', 6: 'A', 7: 'B', 8: 'C', 9: 'D', 10: 'A'}, // 9 is D (correct), 10 is A (wrong)
      scannedAt: DateTime.now().subtract(const Duration(days: 2)),
    );

    await saveCorrectedPapers('q1', [paper1, paper2]);

    // AppSettings
    await saveSettings(AppSettings());

    // Sync status info
    await _prefs!.setString('last_sync_time', DateTime.now().subtract(const Duration(hours: 4)).toIso8601String());
    await _prefs!.setBool('has_unsynced_data', false);
    await _prefs!.setString('user_email', 'atilio@prof-aid.com');
    await _prefs!.setString('subscription_status', 'Premium Ativo (Válido até 31/12/2026)');

    await _prefs!.setBool('initialized', true);
  }

  // --- Institutions ---
  Future<List<Institution>> getInstitutions() async {
    final String? data = _prefs!.getString('institutions');
    if (data == null) return [];
    final List decoded = jsonDecode(data);
    return decoded.map((e) => Institution.fromJson(e)).toList();
  }

  Future<void> saveInstitutions(List<Institution> list) async {
    final String encoded = jsonEncode(list.map((e) => e.toJson()).toList());
    await _prefs!.setString('institutions', encoded);
  }

  // --- Classes ---
  Future<List<Class>> getClasses() async {
    final String? data = _prefs!.getString('classes');
    if (data == null) return [];
    final List decoded = jsonDecode(data);
    return decoded.map((e) => Class.fromJson(e)).toList();
  }

  Future<void> saveClasses(List<Class> list) async {
    final String encoded = jsonEncode(list.map((e) => e.toJson()).toList());
    await _prefs!.setString('classes', encoded);
  }

  // --- Students ---
  Future<List<Student>> getStudents() async {
    final String? data = _prefs!.getString('students');
    if (data == null) return [];
    final List decoded = jsonDecode(data);
    return decoded.map((e) => Student.fromJson(e)).toList();
  }

  Future<void> saveStudents(List<Student> list) async {
    final String encoded = jsonEncode(list.map((e) => e.toJson()).toList());
    await _prefs!.setString('students', encoded);
  }

  Future<void> saveStudent(Student student) async {
    final list = await getStudents();
    final index = list.indexWhere((element) => element.id == student.id);
    if (index != -1) {
      list[index] = student;
    } else {
      list.add(student);
    }
    await saveStudents(list);
    await setUnsynced(true);
  }

  // --- Quizzes ---
  Future<List<Quiz>> getQuizzes() async {
    final String? data = _prefs!.getString('quizzes');
    if (data == null) return [];
    final List decoded = jsonDecode(data);
    return decoded.map((e) => Quiz.fromJson(e)).toList();
  }

  Future<void> saveQuizzes(List<Quiz> list) async {
    final String encoded = jsonEncode(list.map((e) => e.toJson()).toList());
    await _prefs!.setString('quizzes', encoded);
  }

  Future<void> saveQuiz(Quiz quiz) async {
    final list = await getQuizzes();
    final index = list.indexWhere((element) => element.id == quiz.id);
    if (index != -1) {
      list[index] = quiz;
    } else {
      list.add(quiz);
    }
    await saveQuizzes(list);
    await setUnsynced(true);
  }

  // --- Corrected Papers ---
  Future<List<CorrectedPaper>> getCorrectedPapers(String quizId) async {
    final String? data = _prefs!.getString('corrected_papers_$quizId');
    if (data == null) return [];
    final List decoded = jsonDecode(data);
    return decoded.map((e) => CorrectedPaper.fromJson(e)).toList();
  }

  Future<void> saveCorrectedPapers(String quizId, List<CorrectedPaper> list) async {
    final String encoded = jsonEncode(list.map((e) => e.toJson()).toList());
    await _prefs!.setString('corrected_papers_$quizId', encoded);
    
    // Update corrected count in the Quiz
    final quizzes = await getQuizzes();
    final quizIndex = quizzes.indexWhere((q) => q.id == quizId);
    if (quizIndex != -1) {
      quizzes[quizIndex] = quizzes[quizIndex].copyWith(correctedExamsCount: list.length);
      await saveQuizzes(quizzes);
    }
  }

  Future<void> addCorrectedPaper(String quizId, CorrectedPaper paper) async {
    final list = await getCorrectedPapers(quizId);
    
    // Check settings for "one exam per student"
    final settings = await getSettings();
    if (settings.oneExamPerStudent) {
      final index = list.indexWhere((element) => element.studentId == paper.studentId);
      if (index != -1) {
        list[index] = paper;
      } else {
        list.add(paper);
      }
    } else {
      list.add(paper);
    }
    
    await saveCorrectedPapers(quizId, list);

    // Update grade in Student record too
    final students = await getStudents();
    final studentIndex = students.indexWhere((s) => s.id == paper.studentId);
    if (studentIndex != -1) {
      final updatedGrades = Map<String, double>.from(students[studentIndex].quizGrades);
      updatedGrades[quizId] = paper.grade;
      students[studentIndex] = students[studentIndex].copyWith(quizGrades: updatedGrades);
      await saveStudents(students);
    }

    await setUnsynced(true);
  }

  // --- Settings ---
  Future<AppSettings> getSettings() async {
    final String? data = _prefs!.getString('settings');
    if (data == null) return AppSettings();
    return AppSettings.fromJson(jsonDecode(data));
  }

  Future<void> saveSettings(AppSettings settings) async {
    await _prefs!.setString('settings', jsonEncode(settings.toJson()));
  }

  // --- User / Sync details ---
  Future<String> getUserEmail() async {
    return _prefs!.getString('user_email') ?? 'atilio@prof-aid.com';
  }

  Future<void> setUserEmail(String email) async {
    await _prefs!.setString('user_email', email);
  }

  Future<DateTime?> getLastSyncTime() async {
    final timeStr = _prefs!.getString('last_sync_time');
    return timeStr != null ? DateTime.parse(timeStr) : null;
  }

  Future<void> setLastSyncTime(DateTime time) async {
    await _prefs!.setString('last_sync_time', time.toIso8601String());
  }

  Future<bool> hasUnsyncedData() async {
    return _prefs!.getBool('has_unsynced_data') ?? false;
  }

  Future<void> setUnsynced(bool value) async {
    await _prefs!.setBool('has_unsynced_data', value);
  }

  Future<String> getSubscriptionStatus() async {
    return _prefs!.getString('subscription_status') ?? 'Premium Ativo (Válido até 31/12/2026)';
  }

  Future<void> setSubscriptionStatus(String status) async {
    await _prefs!.setString('subscription_status', status);
  }

  Future<String?> getUserToken() async {
    return _prefs!.getString('user_token');
  }

  Future<void> setUserToken(String? token) async {
    if (token == null) {
      await _prefs!.remove('user_token');
    } else {
      await _prefs!.setString('user_token', token);
    }
  }

  Future<void> syncWithCloud() async {
    // Simulate API delay
    await Future.delayed(const Duration(seconds: 2));
    await setLastSyncTime(DateTime.now());
    await setUnsynced(false);
  }

  Future<void> resetAll() async {
    await _prefs!.clear();
    await _prepopulateData();
  }
}
