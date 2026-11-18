import 'dart:io';
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'package:path_provider/path_provider.dart';
import 'package:path/path.dart' as p;
import '../models/app_models.dart';
import '../services/app_state.dart';
import '../theme/app_theme.dart';

class ScanPaperScreen extends StatefulWidget {
  final String quizId;
  const ScanPaperScreen({Key? key, required this.quizId}) : super(key: key);

  @override
  State<ScanPaperScreen> createState() => _ScanPaperScreenState();
}

class _ScanPaperScreenState extends State<ScanPaperScreen> with SingleTickerProviderStateMixin {
  final AppState _appState = AppState();
  CameraController? _cameraController;
  List<CameraDescription> _cameras = [];
  bool _isCameraInitialized = false;
  bool _useSimulator = false;
  bool _isProcessing = false;

  // Scan result state
  CorrectedPaper? _scanResult;
  bool _showMaskOverlay = true;

  // Scanning laser animation
  AnimationController? _animationController;

  // Camera settings controls
  bool _isFlashOn = false;
  double _currentZoom = 1.0;

  @override
  void initState() {
    super.initState();
    _initializeCamera();
    _animationController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 3),
    )..repeat(reverse: true);
  }

  Future<void> _initializeCamera() async {
    try {
      _cameras = await availableCameras();
      if (_cameras.isNotEmpty) {
        // Find back camera
        final backCamera = _cameras.firstWhere(
          (c) => c.lensDirection == CameraLensDirection.back,
          orElse: () => _cameras.first,
        );

        _cameraController = CameraController(
          backCamera,
          ResolutionPreset.high,
          enableAudio: false,
        );

        await _cameraController!.initialize();
        if (mounted) {
          setState(() {
            _isCameraInitialized = true;
          });
        }
      } else {
        setState(() {
          _useSimulator = true;
        });
      }
    } catch (e) {
      // Permission denied or emulator without camera support
      setState(() {
        _useSimulator = true;
      });
    }
  }

  Future<void> _toggleFlash() async {
    if (_cameraController == null || !_cameraController!.value.isInitialized) return;
    try {
      final newMode = _isFlashOn ? FlashMode.off : FlashMode.torch;
      await _cameraController!.setFlashMode(newMode);
      setState(() {
        _isFlashOn = !_isFlashOn;
      });
    } catch (_) {}
  }

  Future<void> _setZoom(double zoom) async {
    if (_cameraController == null || !_cameraController!.value.isInitialized) return;
    try {
      double minZoom = await _cameraController!.getMinZoomLevel();
      double maxZoom = await _cameraController!.getMaxZoomLevel();
      // Clamp for usability
      maxZoom = maxZoom > 4.0 ? 4.0 : maxZoom;
      final targetZoom = zoom.clamp(minZoom, maxZoom);
      await _cameraController!.setZoomLevel(targetZoom);
      setState(() {
        _currentZoom = targetZoom;
      });
    } catch (_) {}
  }

  @override
  void dispose() {
    _cameraController?.dispose();
    _animationController?.dispose();
    super.dispose();
  }

  Future<void> _captureAndProcess() async {
    if (_isProcessing) return;
    
    setState(() {
      _isProcessing = true;
      _scanResult = null;
    });

    String finalPath = '';

    try {
      if (_useSimulator) {
        // Simulator mode: create a dummy file to simulate path
        final directory = await getTemporaryDirectory();
        final file = File(p.join(directory.path, 'simulated_sheet_${DateTime.now().millisecondsSinceEpoch}.jpg'));
        // Create an empty byte file just to satisfy file checks
        await file.writeAsBytes([0, 1, 2, 3]);
        finalPath = file.path;
      } else {
        if (_cameraController == null || !_cameraController!.value.isInitialized) {
          throw Exception('Camera não inicializada');
        }
        
        final XFile rawPhoto = await _cameraController!.takePicture();
        finalPath = rawPhoto.path;
      }

      // Send to python backend / simulation via AppState
      final result = await _appState.scanAndCorrect(
        quizId: widget.quizId,
        imagePath: finalPath,
      );

      if (mounted) {
        setState(() {
          _scanResult = result;
          _isProcessing = false;
        });
        
        // Haptic or vibration feedback if enabled
        if (_appState.settings.vibrateOnScan) {
          // Note: In real production we use vibrate package, we simulate the intent here
        }
      }
    } catch (e) {
      setState(() {
        _isProcessing = false;
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Falha ao processar folha: $e'),
            backgroundColor: AppTheme.error,
          ),
        );
      }
    }
  }

  void _reassignStudent() {
    if (_scanResult == null) return;
    
    final quiz = _appState.quizzes.firstWhere((q) => q.id == widget.quizId);
    final classStudents = _appState.students.where((s) => s.classId == quiz.classId).toList();

    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          backgroundColor: AppTheme.surface,
          title: const Text('Vincular a outro Aluno'),
          content: SizedBox(
            width: double.maxFinite,
            child: ListView.builder(
              shrinkWrap: true,
              itemCount: classStudents.length,
              itemBuilder: (context, index) {
                final student = classStudents[index];
                return ListTile(
                  title: Text(student.name, style: const TextStyle(color: AppTheme.textPrimary)),
                  subtitle: Text('RM: ${student.identifier}', style: const TextStyle(color: AppTheme.textSecondary)),
                  onTap: () async {
                    Navigator.pop(context);
                    
                    // Update scanResult state
                    final updatedResult = _scanResult!.copyWith(
                      studentId: student.id,
                      studentName: student.name,
                    );
                    
                    // Retrieve corrected list, swap and save
                    final list = await _appState.getCorrectedPapers(widget.quizId);
                    final pIdx = list.indexWhere((p) => p.id == _scanResult!.id);
                    if (pIdx != -1) {
                      list[pIdx] = updatedResult;
                      await _appState.updateCorrectedPapersList(widget.quizId, list);
                    }
                    
                    // Update student grade record
                    final updatedGrades = Map<String, double>.from(student.quizGrades);
                    updatedGrades[widget.quizId] = _scanResult!.grade;
                    await _appState.updateStudent(student.copyWith(quizGrades: updatedGrades));

                    setState(() {
                      _scanResult = updatedResult;
                    });
                    
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text('Prova reatribuída para ${student.name}!'),
                        backgroundColor: AppTheme.success,
                      ),
                    );
                  },
                );
              },
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Cancelar', style: TextStyle(color: AppTheme.textSecondary)),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Escanear Prova'),
        backgroundColor: Colors.black,
        actions: [
          if (!_useSimulator && _isCameraInitialized)
            IconButton(
              icon: Icon(_isFlashOn ? Icons.flash_on : Icons.flash_off, color: _isFlashOn ? Colors.amber : Colors.white),
              onPressed: _toggleFlash,
            ),
        ],
      ),
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          // 1. CAMERA PREVIEW OR SIMULATOR VIEWPORT
          Positioned.fill(
            child: _buildCameraViewport(),
          ),

          // 2. SCANNING GRID MASK OVERLAY
          if (_scanResult == null && !_isProcessing)
            Positioned.fill(
              child: _buildScanningMask(),
            ),

          // 3. PROCESSING LOADER
          if (_isProcessing)
            Positioned.fill(
              child: Container(
                color: Colors.black.withOpacity(0.7),
                child: const Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CircularProgressIndicator(color: AppTheme.secondary),
                    SizedBox(height: 16),
                    Text(
                      'Processando gabarito no backend Python...',
                      style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                    ),
                    SizedBox(height: 8),
                    Text(
                      'Alinhando folha com OpenCV...',
                      style: TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                    ),
                  ],
                ),
              ),
            ),

          // 4. GRADING RESULT OVERLAY HUD (REDUCED OPACITY GLASS PANEL)
          if (_scanResult != null)
            Positioned(
              left: 16,
              right: 16,
              bottom: 24,
              child: _buildResultOverlayPanel(),
            ),
            
          // 5. SHUTTER CONTROL TRIGGER (Only shown if scanning is active)
          if (_scanResult == null && !_isProcessing)
            Positioned(
              left: 0,
              right: 0,
              bottom: 40,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (!_useSimulator && _isCameraInitialized) ...[
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        _buildZoomButton('1x', 1.0),
                        const SizedBox(width: 16),
                        _buildZoomButton('2x', 2.0),
                      ],
                    ),
                    const SizedBox(height: 16),
                  ],
                  Center(
                    child: GestureDetector(
                      onTap: _captureAndProcess,
                      child: Container(
                        padding: const EdgeInsets.all(4),
                        decoration: const BoxDecoration(
                          color: Colors.white24,
                          shape: BoxShape.circle,
                        ),
                        child: Container(
                          width: 72,
                          height: 72,
                          decoration: const BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(Icons.camera, color: Colors.black, size: 36),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildCameraViewport() {
    if (_useSimulator) {
      return Container(
        color: const Color(0xFF111827),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.videocam_off, color: Colors.white24, size: 80),
            const SizedBox(height: 16),
            const Text(
              'Simulador de Câmera ProvaGen',
              style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18),
            ),
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 40),
              child: Text(
                _cameras.isEmpty 
                    ? 'Nenhum sensor de câmera física localizado.' 
                    : 'Modo simulador ativado para este ambiente.',
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
              ),
            ),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: _captureAndProcess,
              icon: const Icon(Icons.bolt, color: Colors.white),
              label: const Text('Simular Leitura OMR', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primary,
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
          ],
        ),
      );
    }

    if (!_isCameraInitialized || _cameraController == null) {
      return const Center(
        child: CircularProgressIndicator(color: AppTheme.primary),
      );
    }

    return CameraPreview(_cameraController!);
  }

  Widget _buildScanningMask() {
    return LayoutBuilder(
      builder: (context, constraints) {
        final double width = constraints.maxWidth;
        final double height = constraints.maxHeight;
        
        final double boxWidth = width * 0.85;
        final double boxHeight = height * 0.65;
        
        final double left = (width - boxWidth) / 2;
        final double top = (height - boxHeight) / 3;

        return Stack(
          children: [
            // Darkened backgrounds around focusing box
            ColorFiltered(
              colorFilter: ColorFilter.mode(
                Colors.black.withOpacity(0.5),
                BlendMode.srcOut,
              ),
              child: Stack(
                children: [
                  Container(
                    color: Colors.black,
                  ),
                  Positioned(
                    left: left,
                    top: top,
                    width: boxWidth,
                    height: boxHeight,
                    child: Container(
                      decoration: BoxDecoration(
                        color: Colors.red, // Cutout shape
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            
            // Bounding box frame with corner highlights
            Positioned(
              left: left,
              top: top,
              width: boxWidth,
              height: boxHeight,
              child: Container(
                decoration: BoxDecoration(
                  border: Border.all(color: AppTheme.secondary, width: 2),
                  borderRadius: BorderRadius.circular(16),
                ),
              ),
            ),

             // Corner Targets representing alignment squares for OpenCV homography warping
            _buildAlignmentAnchor(left - 10, top - 10), // Top Left
            _buildAlignmentAnchor(left + boxWidth - 10, top - 10), // Top Right
            _buildAlignmentAnchor(left - 10, top + boxHeight - 10), // Bottom Left
            _buildAlignmentAnchor(left + boxWidth - 10, top + boxHeight - 10), // Bottom Right

            // Pulsing scanning laser line
            if (_animationController != null)
              AnimatedBuilder(
                animation: _animationController!,
                builder: (context, child) {
                  final double laserPosition = top + (boxHeight * _animationController!.value);
                  return Positioned(
                    left: left + 8,
                    width: boxWidth - 16,
                    top: laserPosition,
                    child: Container(
                      height: 3,
                      decoration: BoxDecoration(
                        boxShadow: [
                          BoxShadow(
                            color: AppTheme.secondary.withOpacity(0.6),
                            blurRadius: 8,
                            spreadRadius: 2,
                          )
                        ],
                        gradient: LinearGradient(
                          colors: [
                            AppTheme.secondary.withOpacity(0.0),
                            AppTheme.secondary,
                            AppTheme.secondary.withOpacity(0.0),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),

            // Instruction Text
            Positioned(
              left: 20,
              right: 20,
              top: top - 60,
              child: const Text(
                'Alinhe as 4 marcas pretas do gabarito nos retângulos alvo',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.bold,
                  shadows: [Shadow(color: Colors.black, blurRadius: 4, offset: Offset(0, 1))],
                ),
              ),
            ),
          ],
        );
      },
    );
  }

  Widget _buildAlignmentAnchor(double left, double top) {
    return Positioned(
      left: left,
      top: top,
      width: 20,
      height: 20,
      child: Container(
        decoration: BoxDecoration(
          color: Colors.transparent,
          border: Border.all(color: Colors.amber, width: 3),
        ),
      ),
    );
  }

  Widget _buildResultOverlayPanel() {
    final result = _scanResult!;
    final double passGrade = 6.0;
    final isApproved = result.grade >= passGrade;

    return Container(
      decoration: BoxDecoration(
        color: AppTheme.surface.withOpacity(0.92), // Reduced opacity
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: Colors.white.withOpacity(0.1)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.4),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(20.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            // Top Section: Student details & Grade bubble
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'ALUNO IDENTIFICADO',
                        style: TextStyle(color: AppTheme.secondary, fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 0.5),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        result.studentName,
                        style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'RM: ${result.studentId.toUpperCase()}',
                        style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
                      ),
                    ],
                  ),
                ),
                Container(
                  width: 64,
                  height: 64,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: isApproved ? AppTheme.success.withOpacity(0.15) : AppTheme.error.withOpacity(0.15),
                    shape: BoxShape.circle,
                    border: Border.all(color: isApproved ? AppTheme.success : AppTheme.error, width: 2),
                  ),
                  child: Text(
                    result.grade.toStringAsFixed(1),
                    style: TextStyle(
                      color: isApproved ? AppTheme.success : AppTheme.error,
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
            const Divider(height: 24, color: Colors.white10),
            
            // Stat row: Correct vs Wrong
            if (_appState.settings.showCorrectWrongCount)
              Row(
                children: [
                  const Icon(Icons.check_circle_outline, color: AppTheme.success, size: 18),
                  const SizedBox(width: 6),
                  Text('Acertos: ${result.correctCount}', style: const TextStyle(color: Colors.white70, fontSize: 13)),
                  const SizedBox(width: 20),
                  const Icon(Icons.highlight_off, color: AppTheme.error, size: 18),
                  const SizedBox(width: 6),
                  Text('Erros: ${result.wrongCount}', style: const TextStyle(color: Colors.white70, fontSize: 13)),
                ],
              ),
            if (_appState.settings.showCorrectWrongCount)
              const SizedBox(height: 16),

            // OMR overlay check
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Row(
                  children: [
                    Icon(Icons.remove_red_eye_outlined, color: AppTheme.textSecondary, size: 18),
                    SizedBox(width: 6),
                    Text('Mostrar máscara de correção', style: TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                  ],
                ),
                Switch(
                  value: _showMaskOverlay,
                  activeColor: AppTheme.secondary,
                  onChanged: (val) {
                    setState(() {
                      _showMaskOverlay = val;
                    });
                  },
                ),
              ],
            ),
            
            // Display simulated/actual OpenCV overlay block if enabled
            if (_showMaskOverlay)
              Container(
                margin: const EdgeInsets.only(top: 12),
                height: 180,
                width: double.infinity,
                decoration: BoxDecoration(
                  color: Colors.black,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.white10),
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      // If it's a simulated file path or we have mask
                      _useSimulator
                          ? _buildSimulatedMaskVisuals(result)
                          : Image.file(
                              File(result.correctionMaskPath ?? result.localImagePath!),
                              fit: BoxFit.contain,
                            ),
                      Positioned(
                        right: 8,
                        bottom: 8,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(4)),
                          child: const Text('Visão OpenCV', style: TextStyle(color: Colors.amber, fontSize: 10, fontWeight: FontWeight.bold)),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              
            const SizedBox(height: 16),
            
            // Buttons: Reassign, Scan next
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _reassignStudent,
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: AppTheme.textSecondary),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                    child: const Text('Mudar Aluno', style: TextStyle(color: Colors.white, fontSize: 13)),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      gradient: AppTheme.primaryGradient,
                    ),
                    child: ElevatedButton(
                      onPressed: () {
                        setState(() {
                          _scanResult = null;
                        });
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.transparent,
                        shadowColor: Colors.transparent,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                      child: const Text('Escanear Outro', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold)),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSimulatedMaskVisuals(CorrectedPaper result) {
    // Generate a list of questions bubbles for demonstration
    final List<int> correctQuestions = [];
    final List<int> wrongQuestions = [];
    result.answers.forEach((qNum, ans) {
      // Find if correct in keys
      final quiz = _appState.quizzes.firstWhere((q) => q.id == widget.quizId);
      final key = quiz.questionKeys.firstWhere((k) => k.questionNumber == qNum, orElse: () => QuestionKey(questionNumber: 0, correctOption: '', points: 0));
      if (ans == key.correctOption) {
        correctQuestions.add(qNum);
      } else {
        wrongQuestions.add(qNum);
      }
    });

    return Container(
      color: const Color(0xFF1E293B),
      padding: const EdgeInsets.all(8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Sobreposição de Máscara (Simulado)', style: TextStyle(color: Colors.white54, fontSize: 10, fontWeight: FontWeight.bold)),
              Text('Nota: ${result.grade}', style: const TextStyle(color: AppTheme.success, fontSize: 11, fontWeight: FontWeight.bold)),
            ],
          ),
          const SizedBox(height: 8),
          Expanded(
            child: GridView.builder(
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 5,
                crossAxisSpacing: 6,
                mainAxisSpacing: 6,
                childAspectRatio: 2.2,
              ),
              itemCount: result.answers.length,
              itemBuilder: (context, index) {
                final qNum = index + 1;
                final marked = result.answers[qNum] ?? '';
                final isCorrect = correctQuestions.contains(qNum);
                
                return Container(
                  decoration: BoxDecoration(
                    color: isCorrect ? Colors.green.withOpacity(0.15) : Colors.red.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(
                      color: isCorrect ? AppTheme.success : AppTheme.error,
                      width: 1,
                    ),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text('Q$qNum:', style: const TextStyle(color: Colors.white70, fontSize: 9)),
                      const SizedBox(width: 2),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                        decoration: BoxDecoration(
                          color: isCorrect ? AppTheme.success : AppTheme.error,
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          marked,
                          style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildZoomButton(String label, double zoomValue) {
    final isSelected = _currentZoom == zoomValue;
    return GestureDetector(
      onTap: () => _setZoom(zoomValue),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? AppTheme.secondary : Colors.black54,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isSelected ? AppTheme.secondary : Colors.white24,
            width: 1.5,
          ),
        ),
        child: Text(
          label,
          style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold),
        ),
      ),
    );
  }
}
