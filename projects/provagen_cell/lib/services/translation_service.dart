import 'app_state.dart';

class AppTranslations {
  static final AppTranslations _instance = AppTranslations._internal();
  factory AppTranslations() => _instance;
  AppTranslations._internal();

  static final Map<String, Map<String, String>> _localizedValues = {
    'pt': {
      // General
      'ok': 'OK',
      'cancel': 'Cancelar',
      'save': 'Salvar',
      'loading': 'Carregando...',
      'error': 'Erro',
      'success': 'Sucesso',
      
      // Tabs
      'tab_quizzes': 'Avaliações',
      'tab_classes': 'Turmas',
      'tab_students': 'Alunos',
      'tab_account': 'Conta',

      // Login
      'login_title': 'Acesse o ProvaGen',
      'login_subtitle': 'Conectado ao Prof-Aid',
      'email': 'E-mail',
      'password': 'Senha',
      'btn_login': 'Entrar',
      'login_error': 'Sessão expirada. Faça login novamente.',
      'login_success': 'Sessão iniciada com sucesso.',

      // Quizzes Tab
      'quizzes_title': 'Avaliações',
      'search_quizzes': 'Pesquisar avaliações...',
      'new_quiz': 'Nova Avaliação',
      'create_new_quiz': 'Criar Nova Avaliação',
      'quiz_name': 'Nome do Quiz',
      'select_gabarito': 'Selecione o Gabarito',
      'select_ies': 'Selecione a Instituição',
      'select_class': 'Selecione a Turma',
      'apply_date': 'Data de Aplicação',
      'sort_by': 'Ordenar por',
      'sort_name': 'Nome',
      'sort_date': 'Mais recentes',
      'sort_corrected': 'Provas corrigidas',
      'no_quizzes': 'Nenhuma avaliação cadastrada.',

      // Classes Tab
      'classes_title': 'Turmas por IES',
      'no_classes': 'Nenhuma turma cadastrada.',
      'no_classes_institution': 'Nenhuma turma vinculada a esta instituição.',
      'students_count': 'alunos cadastrados',

      // Students Tab
      'students_title': 'Alunos',
      'search_students': 'Pesquisar alunos...',
      'no_students': 'Nenhum aluno cadastrado.',
      'student_details': 'Detalhes do Aluno',
      'assigned_grades': 'Notas Atribuídas',
      'no_grades_recorded': 'Nenhuma nota registrada para este aluno.',
      'reassign_class': 'Reatribuir Turma',

      // Account Tab
      'account_title': 'Minha Conta',
      'app_language': 'Idioma do Aplicativo',
      'language_desc': 'Escolha entre Inglês, Português e Espanhol',
      'settings_title': 'Configurações do Corretor',
      'settings_subtitle': 'Filtros, contraste, vibração e foco',
      'reset_demo': 'Resetar Dados de Demonstração',
      'reset_demo_subtitle': 'Apaga modificações e recarrega dados iniciais',
      'btn_logout': 'Sair do Aplicativo',
      'subscription_status': 'Status da Assinatura',
      'subscription_active': 'ATIVA',
      'subscription_expired': 'EXPIRADA',
      'validate_online': 'Validar Online',
      'manage_subscription': 'Gerenciar',

      // Quiz Details Screen
      'quiz_details': 'Detalhes do Quiz',
      'btn_scan': 'Escanear Prova',
      'btn_scan_subtitle': 'Corrigir na câmera',
      'btn_edit_key': 'Editar Gabarito',
      'btn_edit_key_subtitle': 'Gabarito oficial',
      'btn_review': 'Revisar Provas',
      'btn_review_subtitle': 'Ver notas e erros',
      'btn_stats': 'Estatísticas',
      'btn_stats_subtitle': 'Análise de acertos',
      'quick_stats': 'Estatísticas Rápidas & Orientação',
      'no_papers_to_stats': 'Nenhuma prova corrigida para este quiz ainda. Use o botão "Escanear Prova" para começar a analisar o desempenho.',
      'class_avg': 'Média da Turma:',
      'passing_rate': 'Taxa de Aprovação',
      'max_min_grade': 'Maior Nota / Menor Nota:',
      'pedagogy_guidance': 'Orientação Pedagógica:',

      // Edit Key Screen
      'edit_key_title': 'Editar Gabarito',
      'global_weight': 'Configurar Peso Geral',
      'question_cancellation': 'Anulação da Questão',
      'cancel_none': 'Ativa (Normal)',
      'cancel_give_points': 'Anular: Dar pontos a todos',
      'cancel_redistribute': 'Anular: Redistribuir pontos',
      'question_number': 'Questão',
      'correct_alternative': 'Gabarito',
      'points_value': 'Pontos',
      'total_quiz_points': 'Pontos Totais da Prova',

      // Scan Paper Screen
      'scan_title': 'Escanear Prova',
      'btn_reassign_student': 'Vincular a outro Aluno',
      'final_grade': 'Nota Final',
      'detected_student': 'ID Aluno Detectado',
      'corrects': 'Acertos',
      'wrongs': 'Erros',
      'camera_preview_mask': 'Sobreposição de Máscara (Simulado)',
      'reassign_success': 'Prova reatribuída com sucesso!',

      // Review Papers Screen
      'review_title': 'Revisar Provas',
      'visual_record': 'Registro Visual da Correção',
      'zoom_instructions': 'Toque para ampliar e ver detalhes',
      'no_visual_record': 'Arquivo de imagem não encontrado localmente.',
      'question_detail': 'Detalhamento das Questões',
      'cancelled_question': 'ANULADA',
      'redistributed_points_tag': '(Pontos Redistribuídos)',
      'given_points_tag': '(Ponto Dado a Todos)',

      // Item Analysis Screen
      'item_analysis_title': 'Análise de Itens',
      'class_performance': 'DESEMPENHO GERAL',
      'performance_pass': 'Rendimento Adequado',
      'performance_fail': 'Abaixo da Média',
      'class_count_calculated': 'Calculado sobre um total de {count} avaliações.',
      'critical_points': 'Pontos Críticos e Destaques',
      'hardest_questions': 'Questões mais erradas (Menor acerto):',
      'easiest_questions': 'Questões mais acertadas (Maior acerto):',
      'hard_tag': 'Crítica',
      'easy_tag': 'Fácil',
      'grade_distribution': 'DISTRIBUIÇÃO DE NOTAS DA TURMA',
      'detail_by_question': 'Detalhamento por Questão',
      'choices_distribution': 'Escolhas dos alunos e taxas de acertos:',
      'distribution_title': 'Distribuição das Alternativas:',
      'strong_distractor_warning': 'Atenção: A alternativa {opt} (incorreta) teve mais marcações do que o gabarito oficial ({correct}). Verifique se há dubiedade no enunciado.',
      'status_backend': 'Status do Servidor',
      'backend_online': 'Conectado ao Python OMR',
      'backend_offline': 'Offline (Usando simulador local)',
      'btn_test_conn': 'Testar Conexão',
      'conn_testing': 'Testando...',
      'conn_success': 'Sucesso: Servidor Python OMR online!',
      'conn_failed': 'Erro: Não foi possível alcançar o servidor.',
    },
    'en': {
      // General
      'ok': 'OK',
      'cancel': 'Cancel',
      'save': 'Save',
      'loading': 'Loading...',
      'error': 'Error',
      'success': 'Success',
      
      // Tabs
      'tab_quizzes': 'Quizzes',
      'tab_classes': 'Classes',
      'tab_students': 'Students',
      'tab_account': 'Account',

      // Login
      'login_title': 'Access ProvaGen',
      'login_subtitle': 'Connected to Prof-Aid',
      'email': 'Email',
      'password': 'Password',
      'btn_login': 'Login',
      'login_error': 'Session expired. Please log in again.',
      'login_success': 'Logged in successfully.',

      // Quizzes Tab
      'quizzes_title': 'Quizzes',
      'search_quizzes': 'Search quizzes...',
      'new_quiz': 'New Quiz',
      'create_new_quiz': 'Create New Quiz',
      'quiz_name': 'Quiz Name',
      'select_gabarito': 'Select Answer Key Template',
      'select_ies': 'Select Institution',
      'select_class': 'Select Class',
      'apply_date': 'Application Date',
      'sort_by': 'Sort by',
      'sort_name': 'Name',
      'sort_date': 'Most recent',
      'sort_corrected': 'Corrected exams',
      'no_quizzes': 'No quizzes registered.',

      // Classes Tab
      'classes_title': 'Classes by Institution',
      'no_classes': 'No classes registered.',
      'no_classes_institution': 'No classes linked to this institution.',
      'students_count': 'registered students',

      // Students Tab
      'students_title': 'Students',
      'search_students': 'Search students...',
      'no_students': 'No students registered.',
      'student_details': 'Student Details',
      'assigned_grades': 'Assigned Grades',
      'no_grades_recorded': 'No grades recorded for this student.',
      'reassign_class': 'Reassign Class',

      // Account Tab
      'account_title': 'My Account',
      'app_language': 'App Language',
      'language_desc': 'Choose between English, Portuguese and Spanish',
      'settings_title': 'Corrector Settings',
      'settings_subtitle': 'Filters, contrast, vibration and focus',
      'reset_demo': 'Reset Demo Data',
      'reset_demo_subtitle': 'Clears modifications and reloads initial data',
      'btn_logout': 'Log Out',
      'subscription_status': 'Subscription Status',
      'subscription_active': 'ACTIVE',
      'subscription_expired': 'EXPIRED',
      'validate_online': 'Validate Online',
      'manage_subscription': 'Manage',

      // Quiz Details Screen
      'quiz_details': 'Quiz Details',
      'btn_scan': 'Scan Paper',
      'btn_scan_subtitle': 'Grade with camera',
      'btn_edit_key': 'Edit Key',
      'btn_edit_key_subtitle': 'Official answer key',
      'btn_review': 'Review Papers',
      'btn_review_subtitle': 'View grades and errors',
      'btn_stats': 'Statistics',
      'btn_stats_subtitle': 'Correct answers rate',
      'quick_stats': 'Quick Stats & Guidance',
      'no_papers_to_stats': 'No papers corrected for this quiz yet. Use the "Scan Paper" button to start analyzing performance.',
      'class_avg': 'Class Average:',
      'passing_rate': 'Passing Rate',
      'max_min_grade': 'Highest Grade / Lowest Grade:',
      'pedagogy_guidance': 'Pedagogical Guidance:',

      // Edit Key Screen
      'edit_key_title': 'Edit Answer Key',
      'global_weight': 'Configure Global Weight',
      'question_cancellation': 'Question Cancellation',
      'cancel_none': 'Active (Normal)',
      'cancel_give_points': 'Cancel: Give points to all',
      'cancel_redistribute': 'Cancel: Redistribute points',
      'question_number': 'Question',
      'correct_alternative': 'Answer',
      'points_value': 'Points',
      'total_quiz_points': 'Total Quiz Points',

      // Scan Paper Screen
      'scan_title': 'Scan Paper',
      'btn_reassign_student': 'Link to another Student',
      'final_grade': 'Final Grade',
      'detected_student': 'Detected Student ID',
      'corrects': 'Corrects',
      'wrongs': 'Wrongs',
      'camera_preview_mask': 'Mask Overlay (Simulated)',
      'reassign_success': 'Paper reassigned successfully!',

      // Review Papers Screen
      'review_title': 'Review Papers',
      'visual_record': 'Visual Correction Record',
      'zoom_instructions': 'Tap to zoom and check details',
      'no_visual_record': 'Image file not found locally.',
      'question_detail': 'Question Breakdown',
      'cancelled_question': 'CANCELLED',
      'redistributed_points_tag': '(Points Redistributed)',
      'given_points_tag': '(Points Given to All)',

      // Item Analysis Screen
      'item_analysis_title': 'Item Analysis',
      'class_performance': 'OVERALL PERFORMANCE',
      'performance_pass': 'Adequate Performance',
      'performance_fail': 'Below Average',
      'class_count_calculated': 'Calculated over a total of {count} corrected exams.',
      'critical_points': 'Critical Points & Highlights',
      'hardest_questions': 'Most missed questions (Lowest correct rate):',
      'easiest_questions': 'Most correct questions (Highest correct rate):',
      'hard_tag': 'Critical',
      'easy_tag': 'Easy',
      'grade_distribution': 'CLASS GRADE DISTRIBUTION',
      'detail_by_question': 'Detailed Breakdown',
      'choices_distribution': 'Student choices and correct rates:',
      'distribution_title': 'Option Distribution:',
      'strong_distractor_warning': 'Warning: Alternative {opt} (incorrect) had more markings than the official key ({correct}). Check for ambiguity in the question text.',
      'status_backend': 'Server Status',
      'backend_online': 'Connected to Python OMR',
      'backend_offline': 'Offline (Using local simulator)',
      'btn_test_conn': 'Test Connection',
      'conn_testing': 'Testing...',
      'conn_success': 'Success: Python OMR server is online!',
      'conn_failed': 'Error: Could not reach the server.',
    },
    'es': {
      // General
      'ok': 'OK',
      'cancel': 'Cancelar',
      'save': 'Guardar',
      'loading': 'Cargando...',
      'error': 'Error',
      'success': 'Éxito',
      
      // Tabs
      'tab_quizzes': 'Exámenes',
      'tab_classes': 'Clases',
      'tab_students': 'Alumnos',
      'tab_account': 'Cuenta',

      // Login
      'login_title': 'Acceder a ProvaGen',
      'login_subtitle': 'Conectado a Prof-Aid',
      'email': 'Correo electrónico',
      'password': 'Contraseña',
      'btn_login': 'Entrar',
      'login_error': 'Sesión expirada. Inicie sesión nuevamente.',
      'login_success': 'Sesión iniciada con éxito.',

      // Quizzes Tab
      'quizzes_title': 'Exámenes',
      'search_quizzes': 'Buscar exámenes...',
      'new_quiz': 'Nuevo Examen',
      'create_new_quiz': 'Crear Nuevo Examen',
      'quiz_name': 'Nombre del Examen',
      'select_gabarito': 'Seleccionar Plantilla de Gabarito',
      'select_ies': 'Seleccionar Institución',
      'select_class': 'Seleccionar Clase',
      'apply_date': 'Fecha de Aplicación',
      'sort_by': 'Ordenar por',
      'sort_name': 'Nombre',
      'sort_date': 'Más recientes',
      'sort_corrected': 'Exámenes corregidos',
      'no_quizzes': 'Ningún examen registrado.',

      // Classes Tab
      'classes_title': 'Clases por Institución',
      'no_classes': 'Ninguna clase registrada.',
      'no_classes_institution': 'Ninguna clase vinculada a esta institución.',
      'students_count': 'alumnos registrados',

      // Students Tab
      'students_title': 'Alumnos',
      'search_students': 'Buscar alumnos...',
      'no_students': 'Ningún alumno registrado.',
      'student_details': 'Detalles del Alumno',
      'assigned_grades': 'Calificaciones Asignadas',
      'no_grades_recorded': 'Ninguna calificación registrada para este alumno.',
      'reassign_class': 'Reasignar Clase',

      // Account Tab
      'account_title': 'Mi Cuenta',
      'app_language': 'Idioma de la App',
      'language_desc': 'Elija entre inglés, portugués y español',
      'settings_title': 'Ajustes del Corrector',
      'settings_subtitle': 'Filtros, contraste, vibración y enfoque',
      'reset_demo': 'Restablecer Datos de Demostración',
      'reset_demo_subtitle': 'Borra modificaciones y recarga datos iniciales',
      'btn_logout': 'Cerrar Sesión',
      'subscription_status': 'Estado de Suscripción',
      'subscription_active': 'ACTIVA',
      'subscription_expired': 'EXPIRADA',
      'validate_online': 'Validar en Línea',
      'manage_subscription': 'Gestionar',

      // Quiz Details Screen
      'quiz_details': 'Detalles del Examen',
      'btn_scan': 'Escanear Prueba',
      'btn_scan_subtitle': 'Corregir con cámara',
      'btn_edit_key': 'Editar Gabarito',
      'btn_edit_key_subtitle': 'Gabarito oficial',
      'btn_review': 'Revisar Pruebas',
      'btn_review_subtitle': 'Ver notas y errores',
      'btn_stats': 'Estadísticas',
      'btn_stats_subtitle': 'Análisis de aciertos',
      'quick_stats': 'Estadísticas Rápidas & Orientación',
      'no_papers_to_stats': 'Ninguna prueba corregida para este examen aún. Use el botón "Escanear Prueba" para comenzar a analizar el rendimiento.',
      'class_avg': 'Promedio de la Clase:',
      'passing_rate': 'Tasa de Aprobación',
      'max_min_grade': 'Nota Mayor / Nota Menor:',
      'pedagogy_guidance': 'Orientación Pedagógica:',

      // Edit Key Screen
      'edit_key_title': 'Editar Gabarito',
      'global_weight': 'Configurar Peso General',
      'question_cancellation': 'Anulación de Pregunta',
      'cancel_none': 'Activa (Normal)',
      'cancel_give_points': 'Anular: Dar puntos a todos',
      'cancel_redistribute': 'Anular: Redistribuir puntos',
      'question_number': 'Pregunta',
      'correct_alternative': 'Gabarito',
      'points_value': 'Puntos',
      'total_quiz_points': 'Puntos Totales de la Prueba',

      // Scan Paper Screen
      'scan_title': 'Escanear Prueba',
      'btn_reassign_student': 'Vincular a otro Alumno',
      'final_grade': 'Nota Final',
      'detected_student': 'ID Alumno Detectado',
      'corrects': 'Aciertos',
      'wrongs': 'Errores',
      'camera_preview_mask': 'Superposición de Máscara (Simulado)',
      'reassign_success': '¡Prueba reasignada con éxito!',

      // Review Papers Screen
      'review_title': 'Revisar Pruebas',
      'visual_record': 'Registro Visual de la Corrección',
      'zoom_instructions': 'Toque para ampliar y ver detalles',
      'no_visual_record': 'Archivo de imagen no encontrado localmente.',
      'question_detail': 'Detalle de las Preguntas',
      'cancelled_question': 'ANULADA',
      'redistributed_points_tag': '(Puntos Redistribuidos)',
      'given_points_tag': '(Punto Dado a Todos)',

      // Item Analysis Screen
      'item_analysis_title': 'Análisis de Ítems',
      'class_performance': 'RENDIMIENTO GENERAL',
      'performance_pass': 'Rendimiento Adecuado',
      'performance_fail': 'Bajo el Promedio',
      'class_count_calculated': 'Calculado sobre un total de {count} exámenes corregidos.',
      'critical_points': 'Puntos Críticos y Destacados',
      'hardest_questions': 'Preguntas más falladas (Menor acierto):',
      'easiest_questions': 'Preguntas más acertadas (Mayor acierto):',
      'hard_tag': 'Crítica',
      'easy_tag': 'Fácil',
      'grade_distribution': 'DISTRIBUCIÓN DE NOTAS DE LA CLASE',
      'detail_by_question': 'Desglose Detallado',
      'choices_distribution': 'Elecciones de alumnos y tasas de acierto:',
      'distribution_title': 'Distribución de Alternativas:',
      'strong_distractor_warning': 'Atención: La alternativa {opt} (incorrecta) tuvo más marcas que el gabarito oficial ({correct}). Verifique si hay ambigüedad en el texto de la pregunta.',
      'status_backend': 'Estado del Servidor',
      'backend_online': 'Conectado al Python OMR',
      'backend_offline': 'Offline (Usando simulador local)',
      'btn_test_conn': 'Probar Conexión',
      'conn_testing': 'Probando...',
      'conn_success': 'Éxito: ¡Servidor Python OMR en línea!',
      'conn_failed': 'Error: No se pudo conectar con el servidor.',
    }
  };

  static String translate(String key, {Map<String, String>? args}) {
    final language = AppState().settings.language;
    final value = _localizedValues[language]?[key] ?? _localizedValues['pt']?[key] ?? key;
    
    if (args != null) {
      String result = value;
      args.forEach((k, v) {
        result = result.replaceAll('{$k}', v);
      });
      return result;
    }
    
    return value;
  }
}
