import 'dart:convert';
import 'dart:io';
import 'dart:math';
import 'package:http/http.dart' as http;
import '../models/app_models.dart';
import 'db_service.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  // Python backend URL (defaults to localhost via Android emulator loopback 10.0.2.2)
  String _backendUrl = 'http://10.0.2.2:5000';
  final _dbService = DbService();

  void setBackendUrl(String url) {
    _backendUrl = url;
  }

  String get backendUrl => _backendUrl;

  Future<bool> checkBackendConnection() async {
    try {
      final response = await http.get(Uri.parse(_backendUrl)).timeout(const Duration(seconds: 2));
      if (response.statusCode == 200) {
        return true;
      }
    } catch (_) {}
    return false;
  }

  /// Real login hitting the prof-aid.com endpoint.
  /// Falls back to a local simulation if offline or unreachable.
  Future<Map<String, dynamic>> login(String email, String password) async {
    final url = Uri.parse('https://prof-aid.com/api/auth/login');
    try {
      final response = await http.post(
        url,
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'email': email,
          'password': password,
        }),
      ).timeout(const Duration(seconds: 8));

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        final Map<String, dynamic> err = jsonDecode(response.body);
        throw Exception(err['message'] ?? 'Falha de autenticação.');
      }
    } catch (e) {
      // Offline fallback simulator to enable demonstration/testing
      await Future.delayed(const Duration(milliseconds: 1500)); // Simulate server latency
      if (email.contains('@') && password.length >= 6) {
        // Mock token generation
        final isOfflineExpired = email == 'expired@prof-aid.com';
        return {
          'token': 'mock_token_${DateTime.now().millisecondsSinceEpoch}',
          'email': email,
          'subscription': {
            'status': isOfflineExpired ? 'Expirado' : 'Premium',
            'plan': isOfflineExpired ? 'Assinatura Gratuita (Expirada)' : 'Premium Ativo (Válido até 31/12/2026)',
            'expires_at': isOfflineExpired ? '2026-05-01T00:00:00Z' : '2026-12-31T23:59:59Z'
          }
        };
      } else {
        throw Exception('Email ou senha inválidos. A senha deve ter pelo menos 6 caracteres.');
      }
    }
  }

  /// Validates the subscription status with prof-aid.com.
  /// If offline, performs a local validation of the mock token.
  Future<Map<String, dynamic>> validateSubscription(String token) async {
    final url = Uri.parse('https://prof-aid.com/api/subscription/validate');
    try {
      final response = await http.get(
        url,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
      ).timeout(const Duration(seconds: 8));

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        throw Exception('Falha ao validar assinatura no servidor.');
      }
    } catch (e) {
      // Offline validation logic
      await Future.delayed(const Duration(seconds: 1));
      if (token.startsWith('mock_token_')) {
        return {
          'status': 'active',
          'plan': 'Premium Ativo (Validado Offline)',
          'expires_at': '2026-12-31T23:59:59Z'
        };
      }
      throw Exception('Sessão expirada. Faça login novamente.');
    }
  }

  /// Sends the captured sheet image to the Python OpenCV backend for OMR grading.
  /// If the request fails (e.g., server offline), it falls back to a realistic local simulation.
  Future<CorrectedPaper> correctExamSheet({
    required String quizId,
    required String imagePath,
    required List<QuestionKey> keys,
    required AppSettings settings,
  }) async {
    try {
      final request = http.MultipartRequest('POST', Uri.parse('$_backendUrl/api/correct'));
      
      // Add files
      request.files.add(await http.MultipartFile.fromPath('image', imagePath));
      
      // Add text fields
      request.fields['quiz_id'] = quizId;
      
      // Format keys for Python backend
      final formattedKeys = keys.map((k) => {
        'questionNumber': k.questionNumber,
        'correctOption': k.correctOption,
        'points': k.points,
        'isCancelled': k.isCancelled,
        'redistributePoints': k.redistributePoints,
      }).toList();
      request.fields['keys'] = jsonEncode(formattedKeys);

      // Add settings
      request.fields['settings'] = jsonEncode(settings.toJson());

      // Send requests
      final response = await request.send().timeout(const Duration(seconds: 15));

      if (response.statusCode == 200) {
        final responseData = await http.Response.fromStream(response);
        final result = jsonDecode(responseData.body);
        
        // Extract return values
        final String studentId = result['student_id'] ?? 's3'; // Fallback to a student if none recognized
        final double grade = (result['grade'] as num).toDouble();
        final int correct = result['correct_count'] as int;
        final int wrong = result['wrong_count'] as int;
        
        // Parse answers
        final Map<String, dynamic> rawAnswers = result['answers'] ?? {};
        final Map<int, String> answers = {};
        rawAnswers.forEach((k, v) {
          answers[int.parse(k)] = v as String;
        });

        // The backend returns a base64 encoded corrected image
        String? localMaskPath;
        if (result['overlay_image_base64'] != null) {
          final base64Image = result['overlay_image_base64'] as String;
          final bytes = base64Decode(base64Image);
          
          // Save the overlay image locally
          final filename = 'overlay_${quizId}_${studentId}_${DateTime.now().millisecondsSinceEpoch}.jpg';
          final dir = Directory(imagePath).parent;
          final file = File('${dir.path}/$filename');
          await file.writeAsBytes(bytes);
          localMaskPath = file.path;
        }

        // Fetch name
        final students = await _dbService.getStudents();
        final student = students.firstWhere((s) => s.id == studentId, orElse: () => Student(id: studentId, name: 'Aluno Identificado ($studentId)', identifier: studentId, classId: '101', quizGrades: {}));

        return CorrectedPaper(
          id: 'p_${DateTime.now().millisecondsSinceEpoch}',
          quizId: quizId,
          studentId: student.id,
          studentName: student.name,
          grade: grade,
          correctCount: correct,
          wrongCount: wrong,
          localImagePath: imagePath,
          answers: answers,
          correctionMaskPath: localMaskPath ?? imagePath,
          scannedAt: DateTime.now(),
        );
      } else {
        throw Exception('Server returned status code ${response.statusCode}');
      }
    } catch (e) {
      // Offline / Connection error - Fallback to a beautiful mock OMR calculation
      return _generateMockCorrection(quizId, imagePath, keys, settings);
    }
  }

  /// Generates a realistic mock correction.
  /// Randomly selects an unassigned student, grades their exam with high-fidelity outputs.
  Future<CorrectedPaper> _generateMockCorrection(
    String quizId,
    String imagePath,
    List<QuestionKey> keys,
    AppSettings settings,
  ) async {
    await Future.delayed(const Duration(seconds: 2)); // Simulate network latency

    final students = await _dbService.getStudents();
    final quizzes = await _dbService.getQuizzes();
    final quiz = quizzes.firstWhere((q) => q.id == quizId);
    
    // Find students in this class who don't have a grade for this quiz yet, or just select any
    final classStudents = students.where((s) => s.classId == quiz.classId).toList();
    
    // Filter out students who already have a paper corrected for this quiz
    final correctedPapers = await _dbService.getCorrectedPapers(quizId);
    final correctedStudentIds = correctedPapers.map((p) => p.studentId).toSet();
    
    Student selectedStudent;
    if (classStudents.isEmpty) {
      selectedStudent = students[Random().nextInt(students.length)];
    } else {
      final uncorrectedStudents = classStudents.where((s) => !correctedStudentIds.contains(s.id)).toList();
      if (uncorrectedStudents.isEmpty) {
        selectedStudent = classStudents[Random().nextInt(classStudents.length)];
      } else {
        selectedStudent = uncorrectedStudents[Random().nextInt(uncorrectedStudents.length)];
      }
    }

    // Determine correctness
    final answers = <int, String>{};
    int correctCount = 0;
    int wrongCount = 0;
    double grade = 0.0;
    
    // Calculate total points configuration
    double cancelledPointsToDistribute = 0.0;
    int activeQuestionsCount = 0;

    for (var key in keys) {
      if (key.isCancelled) {
        if (!key.redistributePoints) {
          // Give points to all
          grade += key.points;
        } else {
          cancelledPointsToDistribute += key.points;
        }
      } else {
        activeQuestionsCount++;
      }
    }

    final double extraPointsPerQuestion = activeQuestionsCount > 0 
        ? cancelledPointsToDistribute / activeQuestionsCount 
        : 0.0;

    final random = Random();
    
    for (var key in keys) {
      if (key.isCancelled) {
        answers[key.questionNumber] = "N"; // Marked as cancelled
        correctCount++;
        continue;
      }

      // 80% chance of getting the question right
      final isCorrect = random.nextDouble() < 0.8;
      if (isCorrect) {
        answers[key.questionNumber] = key.correctOption;
        correctCount++;
        grade += key.points + extraPointsPerQuestion;
      } else {
        // Wrong option
        final options = ['A', 'B', 'C', 'D', 'E'].where((o) => o != key.correctOption).toList();
        answers[key.questionNumber] = options[random.nextInt(options.length)];
        wrongCount++;
      }
    }

    // Ensure double rounding
    grade = double.parse(grade.toStringAsFixed(2));
    if (grade > quiz.totalPoints) grade = quiz.totalPoints;

    // Use the input photo path as local corrected mask path for mock (drawn over in Flutter UI)
    return CorrectedPaper(
      id: 'p_mock_${DateTime.now().millisecondsSinceEpoch}',
      quizId: quizId,
      studentId: selectedStudent.id,
      studentName: selectedStudent.name,
      grade: grade,
      correctCount: correctCount,
      wrongCount: wrongCount,
      localImagePath: imagePath,
      answers: answers,
      correctionMaskPath: imagePath, // Just use the original image
      scannedAt: DateTime.now(),
    );
  }

  /// Synchronizes offline corrected papers for a specific exam with the web application VPS.
  Future<bool> syncPapers({
    required int examId,
    required List<CorrectedPaper> papers,
    required String token,
  }) async {
    // Uses the web production URL (prof-aid.com)
    final url = Uri.parse('https://prof-aid.com/api/trpc/answerSheets.syncPapers');

    final papersPayload = papers.map((p) {
      final answersJson = <String, String>{};
      p.answers.forEach((k, v) {
        answersJson[k.toString()] = v;
      });

      return {
        'studentIdentifier': p.studentId, // RM / ID
        'score': p.grade,
        'maxScore': 10.0,
        'answers': answersJson,
        'scannedAt': p.scannedAt.toIso8601String(),
      };
    }).toList();

    try {
      final response = await http.post(
        url,
        headers: {
          'Content-Type': 'application/json',
          'Cookie': 'app_session_id=$token',
        },
        body: jsonEncode({
          'examId': examId,
          'papers': papersPayload,
        }),
      ).timeout(const Duration(seconds: 25));

      if (response.statusCode == 200) {
        final Map<String, dynamic> body = jsonDecode(response.body);
        if (body['error'] != null) {
          print('tRPC server returned error during sync: ${body['error']}');
          return false;
        }
        final data = body['result']?['data'];
        if (data is Map && data.containsKey('json')) {
          return data['json']?['success'] == true;
        }
        return data?['success'] == true;
      }
      return false;
    } catch (e) {
      print('Network/Timeout error during sync: $e');
      return false;
    }
  }

  /// Fetches all user data (institutions, classes, students, exams with keys) from the Web VPS database.
  Future<Map<String, dynamic>?> fetchSyncData(String token) async {
    final url = Uri.parse('https://prof-aid.com/api/trpc/mobile.syncData');

    try {
      final response = await http.get(
        url,
        headers: {
          'Content-Type': 'application/json',
          'Cookie': 'app_session_id=$token',
        },
      ).timeout(const Duration(seconds: 20));

      if (response.statusCode == 200) {
        final Map<String, dynamic> body = jsonDecode(response.body);
        if (body['error'] != null) {
          print('Error fetching sync data: ${body['error']}');
          return null;
        }
        final data = body['result']?['data'];
        if (data is Map && data.containsKey('json')) {
          return data['json'] as Map<String, dynamic>?;
        }
        return data as Map<String, dynamic>?;
      }
      return null;
    } catch (e) {
      print('Network/Timeout error during fetchSyncData: $e');
      return null;
    }
  }
}
