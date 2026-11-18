import 'package:flutter/material.dart';
import 'package:odu_journey/data/repository/progress_repository.dart';
import 'package:odu_journey/l10n/manual_translations.dart';
import 'package:odu_journey/services/api_service.dart';
import 'package:odu_journey/utils/app_locale.dart';

class OnboardingScreen extends StatefulWidget {
  final VoidCallback onComplete;

  const OnboardingScreen({super.key, required this.onComplete});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  final _pageController  = PageController();
  final _nameController  = TextEditingController();
  final _emailController = TextEditingController();
  final _repo = ProgressRepository();

  int _currentPage = 0;
  String? _selectedLanguage;
  bool _termsAccepted = false;
  bool _disclaimerAccepted = false;

  @override
  void dispose() {
    _pageController.dispose();
    _nameController.dispose();
    _emailController.dispose();
    super.dispose();
  }

  bool get _canProceed {
    switch (_currentPage) {
      case 0:
        return _selectedLanguage != null;
      case 1:
        return true;
      case 2:
        return _termsAccepted;
      case 3:
        return _disclaimerAccepted;
      default:
        return false;
    }
  }

  void _selectLanguage(String code) {
    setState(() => _selectedLanguage = code);
    appLocaleNotifier.value = Locale(code);
  }

  void _nextPage() {
    if (_currentPage < 3) {
      _pageController.nextPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
    } else {
      _finish();
    }
  }

  Future<void> _finish() async {
    await _repo.setUserLanguage(_selectedLanguage!);
    await _repo.setUserDisplayName(_nameController.text);
    await _repo.setOnboardingComplete();

    final email = _emailController.text.trim();
    if (email.isNotEmpty && _isValidEmail(email)) {
      await _repo.setUserEmail(email);
      final deviceId = await _repo.getDeviceId();
      ApiService().registerUser(
        email: email,
        displayName: _nameController.text.trim(),
        deviceId: deviceId,
        language: _selectedLanguage!,
      );
    }

    widget.onComplete();
  }

  bool _isValidEmail(String email) {
    return RegExp(r'^[\w\.\-]+@[\w\-]+\.[a-zA-Z]{2,}$').hasMatch(email);
  }

  String _t(String key) {
    return ManualTranslations.of(context)?.translate(key) ?? key;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [Color(0xFF1A1A2E), Color(0xFF16213E)],
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
              ),
            ),
          ),
          SafeArea(
            child: Column(
              children: [
                _buildStepIndicator(),
                Expanded(
                  child: PageView(
                    controller: _pageController,
                    physics: const NeverScrollableScrollPhysics(),
                    onPageChanged: (p) => setState(() => _currentPage = p),
                    children: [
                      _buildLanguagePage(),
                      _buildNamePage(),
                      _buildTermsPage(),
                      _buildDisclaimerPage(),
                    ],
                  ),
                ),
                _buildBottomNav(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStepIndicator() {
    return Padding(
      padding: const EdgeInsets.only(top: 24, bottom: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: List.generate(4, (i) {
          return AnimatedContainer(
            duration: const Duration(milliseconds: 250),
            margin: const EdgeInsets.symmetric(horizontal: 4),
            width: _currentPage == i ? 28 : 8,
            height: 8,
            decoration: BoxDecoration(
              color: _currentPage == i ? Colors.amber : Colors.white24,
              borderRadius: BorderRadius.circular(4),
            ),
          );
        }),
      ),
    );
  }

  // ─── PAGE 0: LANGUAGE ──────────────────────────────────────────────────────

  Widget _buildLanguagePage() {
    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 16),
      child: Column(
        children: [
          const Icon(Icons.language, size: 72, color: Colors.amber),
          const SizedBox(height: 24),
          const Text(
            'Select your language\nSelecione seu idioma\nSeleccione su idioma',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 15,
              color: Colors.white60,
              height: 1.7,
            ),
          ),
          const SizedBox(height: 36),
          _buildLanguageCard('pt', '🇧🇷', 'Português', 'Brasil / Portugal'),
          const SizedBox(height: 14),
          _buildLanguageCard('en', '🇺🇸', 'English', 'United States / United Kingdom'),
          const SizedBox(height: 14),
          _buildLanguageCard('es', '🇪🇸', 'Español', 'España / Latinoamérica'),
        ],
      ),
    );
  }

  Widget _buildLanguageCard(String code, String flag, String name, String region) {
    final isSelected = _selectedLanguage == code;
    return GestureDetector(
      onTap: () => _selectLanguage(code),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
        decoration: BoxDecoration(
          border: Border.all(
            color: isSelected ? Colors.amber : Colors.white24,
            width: isSelected ? 2 : 1,
          ),
          borderRadius: BorderRadius.circular(14),
          color: isSelected ? Colors.amber.withValues(alpha: 0.12) : Colors.white.withValues(alpha: 0.04),
        ),
        child: Row(
          children: [
            Text(flag, style: const TextStyle(fontSize: 32)),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    style: TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.bold,
                      color: isSelected ? Colors.amber : Colors.white,
                    ),
                  ),
                  Text(
                    region,
                    style: const TextStyle(fontSize: 12, color: Colors.white54),
                  ),
                ],
              ),
            ),
            if (isSelected)
              const Icon(Icons.check_circle, color: Colors.amber, size: 22),
          ],
        ),
      ),
    );
  }

  // ─── PAGE 1: DISPLAY NAME ─────────────────────────────────────────────────

  Widget _buildNamePage() {
    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 16),
      child: Column(
        children: [
          const Icon(Icons.person_outline, size: 72, color: Colors.amber),
          const SizedBox(height: 24),
          Text(
            _t('onboarding_name_title'),
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.bold,
              color: Colors.white,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 32),
          TextField(
            controller: _nameController,
            style: const TextStyle(color: Colors.white, fontSize: 16),
            decoration: InputDecoration(
              hintText: _t('onboarding_name_hint'),
              hintStyle: const TextStyle(color: Colors.white38),
              filled: true,
              fillColor: Colors.white.withValues(alpha: 0.07),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: Colors.white24),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: Colors.white24),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: Colors.amber, width: 2),
              ),
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            ),
            textCapitalization: TextCapitalization.words,
          ),
          const SizedBox(height: 12),
          Text(
            _t('onboarding_name_optional'),
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 12, color: Colors.white38),
          ),
          const SizedBox(height: 28),
          const Divider(color: Colors.white12),
          const SizedBox(height: 20),
          Align(
            alignment: Alignment.centerLeft,
            child: Text(
              _t('onboarding_email_label'),
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: Colors.white54,
                letterSpacing: 0.5,
              ),
            ),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _emailController,
            style: const TextStyle(color: Colors.white, fontSize: 16),
            keyboardType: TextInputType.emailAddress,
            decoration: InputDecoration(
              hintText: _t('onboarding_email_hint'),
              hintStyle: const TextStyle(color: Colors.white38),
              prefixIcon: const Icon(Icons.email_outlined, color: Colors.white38, size: 20),
              filled: true,
              fillColor: Colors.white.withValues(alpha: 0.07),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: Colors.white24),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: Colors.white24),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: Colors.amber, width: 2),
              ),
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            _t('onboarding_email_sub'),
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 12, color: Colors.white38),
          ),
        ],
      ),
    );
  }

  // ─── PAGE 2: TERMS OF SERVICE ────────────────────────────────────────────

  Widget _buildTermsPage() {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 8),
          child: Text(
            _t('onboarding_terms_title'),
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.bold,
              color: Colors.white,
            ),
          ),
        ),
        Expanded(
          child: Container(
            margin: const EdgeInsets.symmetric(horizontal: 20),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.06),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.white12),
            ),
            child: SingleChildScrollView(
              child: Text(
                _termsText(),
                style: const TextStyle(
                  fontSize: 13,
                  color: Colors.white70,
                  height: 1.7,
                ),
              ),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          child: GestureDetector(
            onTap: () => setState(() => _termsAccepted = !_termsAccepted),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  width: 22,
                  height: 22,
                  margin: const EdgeInsets.only(top: 1),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(4),
                    border: Border.all(
                      color: _termsAccepted ? Colors.amber : Colors.white38,
                      width: 2,
                    ),
                    color: _termsAccepted ? Colors.amber : Colors.transparent,
                  ),
                  child: _termsAccepted
                      ? const Icon(Icons.check, size: 14, color: Colors.black)
                      : null,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    _t('onboarding_terms_accept'),
                    style: TextStyle(
                      fontSize: 13,
                      color: _termsAccepted ? Colors.amber : Colors.white60,
                      height: 1.5,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  // ─── PAGE 3: EDUCATIONAL DISCLAIMER ─────────────────────────────────────

  Widget _buildDisclaimerPage() {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 8),
          child: Text(
            _t('onboarding_disclaimer_title'),
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.bold,
              color: Colors.white,
            ),
          ),
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            children: [
              _buildDisclaimerItem(
                Icons.school_outlined,
                Colors.amber,
                _disclaimerItemTitle(0),
                _disclaimerItemBody(0),
              ),
              _buildDisclaimerItem(
                Icons.history_edu_outlined,
                const Color(0xFF5D4037),
                _disclaimerItemTitle(1),
                _disclaimerItemBody(1),
              ),
              _buildDisclaimerItem(
                Icons.person_off_outlined,
                const Color(0xFF6A1B9A),
                _disclaimerItemTitle(2),
                _disclaimerItemBody(2),
              ),
              _buildDisclaimerItem(
                Icons.gavel_rounded,
                const Color(0xFFC62828),
                _disclaimerItemTitle(3),
                _disclaimerItemBody(3),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          child: GestureDetector(
            onTap: () => setState(() => _disclaimerAccepted = !_disclaimerAccepted),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  width: 22,
                  height: 22,
                  margin: const EdgeInsets.only(top: 1),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(4),
                    border: Border.all(
                      color: _disclaimerAccepted ? Colors.amber : Colors.white38,
                      width: 2,
                    ),
                    color: _disclaimerAccepted ? Colors.amber : Colors.transparent,
                  ),
                  child: _disclaimerAccepted
                      ? const Icon(Icons.check, size: 14, color: Colors.black)
                      : null,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    _t('onboarding_disclaimer_accept'),
                    style: TextStyle(
                      fontSize: 13,
                      color: _disclaimerAccepted ? Colors.amber : Colors.white60,
                      height: 1.5,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildDisclaimerItem(IconData icon, Color color, String title, String body) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title,
                    style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: color)),
                const SizedBox(height: 4),
                Text(body,
                    style: const TextStyle(
                        fontSize: 12, color: Colors.white60, height: 1.4)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ─── BOTTOM NAVIGATION ────────────────────────────────────────────────────

  Widget _buildBottomNav() {
    final isLastPage = _currentPage == 3;
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 4, 24, 20),
      child: SizedBox(
        width: double.infinity,
        child: ElevatedButton(
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.amber,
            disabledBackgroundColor: Colors.white12,
            foregroundColor: Colors.black,
            disabledForegroundColor: Colors.white38,
            padding: const EdgeInsets.symmetric(vertical: 16),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
          ),
          onPressed: _canProceed ? _nextPage : null,
          child: Text(
            isLastPage ? _t('onboarding_start') : _t('next'),
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          ),
        ),
      ),
    );
  }

  // ─── LOCALIZED CONTENT ────────────────────────────────────────────────────

  String _termsText() {
    final lang = _selectedLanguage ?? 'pt';
    switch (lang) {
      case 'en':
        return '''Odù Journey is a digital tool for studying the Ifá oracle system. By using this application, you agree to the following terms:

PURPOSE
This application is intended exclusively for educational and personal research purposes. It does not replace traditional oral transmission or the guidance of experienced masters.

PRIVACY
Your study data is stored locally on your device. We do not collect, transmit or share personal information.

CONTENT LIMITATIONS
Despite our careful work, content may contain inaccuracies or incomplete interpretations. Always consult traditional sources and people of knowledge.

RESPONSIBLE USE
The application must not be used for professional divination, religious consultation, or any practice requiring sacerdotal training or initiation.

UPDATES
These terms may be updated periodically. Continued use of the app implies acceptance of the updated conditions.''';

      case 'es':
        return '''Odù Journey es una herramienta digital para el estudio del sistema oracular de Ifá. Al utilizar esta aplicación, usted acepta los siguientes términos:

FINALIDAD
Esta aplicación está destinada exclusivamente a fines educativos y de investigación personal. No reemplaza la transmisión oral tradicional ni la orientación de maestros experimentados.

PRIVACIDAD
Sus datos de estudio se almacenan localmente en su dispositivo. No recopilamos, transmitimos ni compartimos información personal.

LIMITACIONES DE CONTENIDO
A pesar de nuestro cuidadoso trabajo, el contenido puede contener imprecisiones o interpretaciones incompletas. Consulte siempre fuentes tradicionales y personas de conocimiento.

USO RESPONSABLE
La aplicación no debe utilizarse para adivinación profesional, consulta religiosa ni ninguna práctica que requiera formación sacerdotal o iniciación.

ACTUALIZACIONES
Estos términos pueden actualizarse periódicamente. El uso continuado de la app implica la aceptación de las condiciones actualizadas.''';

      default: // 'pt'
        return '''O Odù Journey é uma ferramenta digital de apoio ao estudo do sistema oracular de Ifá. Ao usar este aplicativo, você concorda com os seguintes termos:

FINALIDADE
Este aplicativo destina-se exclusivamente a fins educacionais e de pesquisa pessoal. Não substitui a transmissão oral tradicional nem a orientação de mestres experientes.

PRIVACIDADE
Seus dados de estudo são armazenados localmente no seu dispositivo. Não coletamos, transmitimos ou compartilhamos informações pessoais.

LIMITAÇÕES DE CONTEÚDO
Embora tenhamos trabalhado com cuidado, o conteúdo pode conter imprecisões ou interpretações incompletas. Sempre consulte fontes tradicionais e pessoas de conhecimento.

USO RESPONSÁVEL
O aplicativo não deve ser utilizado para adivinhação profissional, consulta religiosa ou qualquer prática que requeira formação sacerdotal ou iniciação.

ATUALIZAÇÕES
Estes termos podem ser atualizados periodicamente. O uso continuado do app implica aceitação das condições atualizadas.''';
    }
  }

  String _disclaimerItemTitle(int index) {
    final lang = _selectedLanguage ?? 'pt';
    const titles = {
      'pt': [
        'Fins exclusivamente educacionais',
        'Não substitui a tradição oral',
        'Não forma nem substitui um babalawo',
        'Não autoriza a prática sacerdotal',
      ],
      'en': [
        'Exclusively educational purposes',
        'Does not replace oral tradition',
        'Does not train or replace a babalawo',
        'Does not authorize sacerdotal practice',
      ],
      'es': [
        'Fines exclusivamente educativos',
        'No reemplaza la tradición oral',
        'No forma ni reemplaza a un babalawo',
        'No autoriza la práctica sacerdotal',
      ],
    };
    return titles[lang]?[index] ?? titles['pt']![index];
  }

  String _disclaimerItemBody(int index) {
    final lang = _selectedLanguage ?? 'pt';
    const bodies = {
      'pt': [
        'O app oferece conteúdo para memorização e pesquisa. Não representa uma formação completa no sistema de Ifá.',
        'O conhecimento de Ifá é transmitido pela tradição oral, pelos mais velhos e guardiões do saber ancestral. Nenhum app substitui essa experiência.',
        'O uso deste aplicativo não confere título, status ou responsabilidades de um babalawo. A formação ocorre exclusivamente na tradição.',
        'Este app não serve como certificação ou autorização para funções sacerdotais ou religiosas. A autoridade é concedida pela tradição.',
      ],
      'en': [
        'The app provides content for memorization and research. It does not represent a complete training in the Ifá system.',
        'Ifá knowledge is transmitted through oral tradition, elders and guardians of ancestral wisdom. No app replaces that experience.',
        'Using this app does not confer the title, status or responsibilities of a babalawo. Training occurs exclusively within the tradition.',
        'This app does not serve as certification or authorization for sacerdotal or religious functions. Authority is granted by tradition.',
      ],
      'es': [
        'La app ofrece contenido para memorización e investigación. No representa una formación completa en el sistema de Ifá.',
        'El conocimiento de Ifá se transmite por tradición oral, los mayores y guardianes del saber ancestral. Ninguna app reemplaza esa experiencia.',
        'El uso de esta app no otorga título, estatus ni responsabilidades de un babalawo. La formación ocurre exclusivamente en la tradición.',
        'Esta app no sirve como certificación o autorización para funciones sacerdotales o religiosas. La autoridad la concede la tradición.',
      ],
    };
    return bodies[lang]?[index] ?? bodies['pt']![index];
  }
}
