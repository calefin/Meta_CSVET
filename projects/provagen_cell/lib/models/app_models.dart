
class Institution {
  final String id;
  final String name;

  Institution({required this.id, required this.name});

  factory Institution.fromJson(Map<String, dynamic> json) {
    return Institution(
      id: json['id'] as String,
      name: json['name'] as String,
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
  };
}

class Class {
  final String id;
  final String name;
  final String institutionId;

  Class({required this.id, required this.name, required this.institutionId});

  factory Class.fromJson(Map<String, dynamic> json) {
    return Class(
      id: json['id'] as String,
      name: json['name'] as String,
      institutionId: json['institutionId'] as String,
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'institutionId': institutionId,
  };
}

class Student {
  final String id;
  final String name;
  final String identifier; // e.g., registration number
  final String classId;
  final Map<String, double> quizGrades; // quizId -> score

  Student({
    required this.id,
    required this.name,
    required this.identifier,
    required this.classId,
    required this.quizGrades,
  });

  factory Student.fromJson(Map<String, dynamic> json) {
    return Student(
      id: json['id'] as String,
      name: json['name'] as String,
      identifier: json['identifier'] as String,
      classId: json['classId'] as String,
      quizGrades: Map<String, double>.from(json['quizGrades'] ?? {}),
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'identifier': identifier,
    'classId': classId,
    'quizGrades': quizGrades,
  };

  Student copyWith({
    String? name,
    String? identifier,
    String? classId,
    Map<String, double>? quizGrades,
  }) {
    return Student(
      id: id,
      name: name ?? this.name,
      identifier: identifier ?? this.identifier,
      classId: classId ?? this.classId,
      quizGrades: quizGrades ?? this.quizGrades,
    );
  }
}

class QuestionKey {
  final int questionNumber;
  String correctOption; // "A", "B", "C", "D", "E", or "N" (none/cancelled)
  double points;
  bool isCancelled;
  bool redistributePoints; // if true, redistribute point to other questions. If false, give points to all.

  QuestionKey({
    required this.questionNumber,
    required this.correctOption,
    required this.points,
    this.isCancelled = false,
    this.redistributePoints = false,
  });

  factory QuestionKey.fromJson(Map<String, dynamic> json) {
    return QuestionKey(
      questionNumber: json['questionNumber'] as int,
      correctOption: json['correctOption'] as String,
      points: (json['points'] as num).toDouble(),
      isCancelled: json['isCancelled'] as bool? ?? false,
      redistributePoints: json['redistributePoints'] as bool? ?? false,
    );
  }

  Map<String, dynamic> toJson() => {
    'questionNumber': questionNumber,
    'correctOption': correctOption,
    'points': points,
    'isCancelled': isCancelled,
    'redistributePoints': redistributePoints,
  };
}

class Quiz {
  final String id;
  final String name;
  final String gabaritoTemplate; // e.g. "Template 20 Questões", "Template 50 Questões"
  final DateTime date;
  final String institutionId;
  final String classId;
  final int questionsCount;
  final double totalPoints;
  final int correctedExamsCount;
  final List<QuestionKey> questionKeys;

  Quiz({
    required this.id,
    required this.name,
    required this.gabaritoTemplate,
    required this.date,
    required this.institutionId,
    required this.classId,
    required this.questionsCount,
    required this.totalPoints,
    required this.correctedExamsCount,
    required this.questionKeys,
  });

  factory Quiz.fromJson(Map<String, dynamic> json) {
    var keysList = json['questionKeys'] as List? ?? [];
    return Quiz(
      id: json['id'] as String,
      name: json['name'] as String,
      gabaritoTemplate: json['gabaritoTemplate'] as String,
      date: DateTime.parse(json['date'] as String),
      institutionId: json['institutionId'] as String,
      classId: json['classId'] as String,
      questionsCount: json['questionsCount'] as int,
      totalPoints: (json['totalPoints'] as num).toDouble(),
      correctedExamsCount: json['correctedExamsCount'] as int,
      questionKeys: keysList.map((e) => QuestionKey.fromJson(e)).toList(),
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'gabaritoTemplate': gabaritoTemplate,
    'date': date.toIso8601String(),
    'institutionId': institutionId,
    'classId': classId,
    'questionsCount': questionsCount,
    'totalPoints': totalPoints,
    'correctedExamsCount': correctedExamsCount,
    'questionKeys': questionKeys.map((e) => e.toJson()).toList(),
  };

  Quiz copyWith({
    String? name,
    String? gabaritoTemplate,
    DateTime? date,
    String? institutionId,
    String? classId,
    int? questionsCount,
    double? totalPoints,
    int? correctedExamsCount,
    List<QuestionKey>? questionKeys,
  }) {
    return Quiz(
      id: id,
      name: name ?? this.name,
      gabaritoTemplate: gabaritoTemplate ?? this.gabaritoTemplate,
      date: date ?? this.date,
      institutionId: institutionId ?? this.institutionId,
      classId: classId ?? this.classId,
      questionsCount: questionsCount ?? this.questionsCount,
      totalPoints: totalPoints ?? this.totalPoints,
      correctedExamsCount: correctedExamsCount ?? this.correctedExamsCount,
      questionKeys: questionKeys ?? this.questionKeys,
    );
  }
}

class CorrectedPaper {
  final String id;
  final String quizId;
  String studentId; // Can be updated if reassigned
  String studentName; // Can be updated
  final double grade;
  final int correctCount;
  final int wrongCount;
  final String? localImagePath;
  final Map<int, String> answers; // questionNumber -> markedOption ("A", "B", "C", "D", "E", or "")
  final String? correctionMaskPath; // local image path with overlays
  final DateTime scannedAt;

  CorrectedPaper({
    required this.id,
    required this.quizId,
    required this.studentId,
    required this.studentName,
    required this.grade,
    required this.correctCount,
    required this.wrongCount,
    this.localImagePath,
    required this.answers,
    this.correctionMaskPath,
    required this.scannedAt,
  });

  factory CorrectedPaper.fromJson(Map<String, dynamic> json) {
    Map<String, dynamic> rawAnswers = json['answers'] ?? {};
    Map<int, String> parsedAnswers = {};
    rawAnswers.forEach((key, value) {
      parsedAnswers[int.parse(key)] = value as String;
    });

    return CorrectedPaper(
      id: json['id'] as String,
      quizId: json['quizId'] as String,
      studentId: json['studentId'] as String,
      studentName: json['studentName'] as String,
      grade: (json['grade'] as num).toDouble(),
      correctCount: json['correctCount'] as int,
      wrongCount: json['wrongCount'] as int,
      localImagePath: json['localImagePath'] as String?,
      answers: parsedAnswers,
      correctionMaskPath: json['correctionMaskPath'] as String?,
      scannedAt: DateTime.parse(json['scannedAt'] as String),
    );
  }

  Map<String, dynamic> toJson() {
    Map<String, String> stringAnswers = {};
    answers.forEach((key, value) {
      stringAnswers[key.toString()] = value;
    });

    return {
      'id': id,
      'quizId': quizId,
      'studentId': studentId,
      'studentName': studentName,
      'grade': grade,
      'correctCount': correctCount,
      'wrongCount': wrongCount,
      'localImagePath': localImagePath,
      'answers': stringAnswers,
      'correctionMaskPath': correctionMaskPath,
      'scannedAt': scannedAt.toIso8601String(),
    };
  }

  CorrectedPaper copyWith({
    String? studentId,
    String? studentName,
    double? grade,
    int? correctCount,
    int? wrongCount,
    Map<int, String>? answers,
    String? correctionMaskPath,
    String? localImagePath,
  }) {
    return CorrectedPaper(
      id: id,
      quizId: quizId,
      studentId: studentId ?? this.studentId,
      studentName: studentName ?? this.studentName,
      grade: grade ?? this.grade,
      correctCount: correctCount ?? this.correctCount,
      wrongCount: wrongCount ?? this.wrongCount,
      localImagePath: localImagePath ?? this.localImagePath,
      answers: answers ?? this.answers,
      correctionMaskPath: correctionMaskPath ?? this.correctionMaskPath,
      scannedAt: scannedAt,
    );
  }
}

class AppSettings {
  bool showPaperReviewOnCamera;
  bool oneExamPerStudent;
  bool warnDuplicateStudent;
  bool hideSummaryResult;
  bool showCorrectWrongCount;
  bool shutterSound;
  bool vibrateOnScan;
  bool brightLightDetector;
  bool adjustContrast;
  int focusRequirement; // 1 to 10
  String sheetStrictness; // "Most Strict", "Least Strict"
  String resolutionSelection; // "Force Highest", "Dynamic"
  bool crashReports;
  bool shareLogs;
  bool showDebugImages;
  String backendUrl;
  String language; // "pt", "en", "es"

  AppSettings({
    this.showPaperReviewOnCamera = true,
    this.oneExamPerStudent = true,
    this.warnDuplicateStudent = true,
    this.hideSummaryResult = false,
    this.showCorrectWrongCount = true,
    this.shutterSound = false,
    this.vibrateOnScan = true,
    this.brightLightDetector = false,
    this.adjustContrast = false,
    this.focusRequirement = 5,
    this.sheetStrictness = "Most Strict",
    this.resolutionSelection = "Dynamic",
    this.crashReports = true,
    this.shareLogs = true,
    this.showDebugImages = false,
    this.backendUrl = 'http://10.0.2.2:5000',
    this.language = "pt",
  });

  factory AppSettings.fromJson(Map<String, dynamic> json) {
    return AppSettings(
      showPaperReviewOnCamera: json['showPaperReviewOnCamera'] as bool? ?? true,
      oneExamPerStudent: json['oneExamPerStudent'] as bool? ?? true,
      warnDuplicateStudent: json['warnDuplicateStudent'] as bool? ?? true,
      hideSummaryResult: json['hideSummaryResult'] as bool? ?? false,
      showCorrectWrongCount: json['showCorrectWrongCount'] as bool? ?? true,
      shutterSound: json['shutterSound'] as bool? ?? false,
      vibrateOnScan: json['vibrateOnScan'] as bool? ?? true,
      brightLightDetector: json['brightLightDetector'] as bool? ?? false,
      adjustContrast: json['adjustContrast'] as bool? ?? false,
      focusRequirement: json['focusRequirement'] as int? ?? 5,
      sheetStrictness: json['sheetStrictness'] as String? ?? "Most Strict",
      resolutionSelection: json['resolutionSelection'] as String? ?? "Dynamic",
      crashReports: json['crashReports'] as bool? ?? true,
      shareLogs: json['shareLogs'] as bool? ?? true,
      showDebugImages: json['showDebugImages'] as bool? ?? false,
      backendUrl: json['backendUrl'] as String? ?? 'http://10.0.2.2:5000',
      language: json['language'] as String? ?? "pt",
    );
  }

  Map<String, dynamic> toJson() => {
    'showPaperReviewOnCamera': showPaperReviewOnCamera,
    'oneExamPerStudent': oneExamPerStudent,
    'warnDuplicateStudent': warnDuplicateStudent,
    'hideSummaryResult': hideSummaryResult,
    'showCorrectWrongCount': showCorrectWrongCount,
    'shutterSound': shutterSound,
    'vibrateOnScan': vibrateOnScan,
    'brightLightDetector': brightLightDetector,
    'adjustContrast': adjustContrast,
    'focusRequirement': focusRequirement,
    'sheetStrictness': sheetStrictness,
    'resolutionSelection': resolutionSelection,
    'crashReports': crashReports,
    'shareLogs': shareLogs,
    'showDebugImages': showDebugImages,
    'backendUrl': backendUrl,
    'language': language,
  };
}
