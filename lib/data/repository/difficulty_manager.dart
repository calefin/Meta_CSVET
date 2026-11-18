import 'package:odu_journey/data/models/odu.dart';
import 'package:odu_journey/data/odu_patterns.dart';
import 'package:odu_journey/data/repository/odu_repository.dart';
import 'package:odu_journey/data/repository/progress_repository.dart';

enum DifficultyLevel {
  meji,
  omolu,
}

class DifficultyManager {
  final ProgressRepository _progressRepository = ProgressRepository();

  Future<DifficultyLevel> getCurrentLevel() async {
    final isUnlocked = await _progressRepository.is256Unlocked();
    return isUnlocked ? DifficultyLevel.omolu : DifficultyLevel.meji;
  }

  Future<List<Odu>> getOdusForTraining() async {
    final level = await getCurrentLevel();
    final allOdus = await OduRepository().getAllOdus(null);

    if (level == DifficultyLevel.meji) {
      return allOdus.where((odu) => oduNames.contains(odu.nome)).toList();
    } else {
      return allOdus;
    }
  }
}
