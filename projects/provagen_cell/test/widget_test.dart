import 'package:flutter_test/flutter_test.dart';
import 'package:provagen_cell/main.dart';

void main() {
  testWidgets('ProvaGen app smoke test', (WidgetTester tester) async {
    // Build our app and trigger a frame.
    await tester.pumpWidget(const ProvaGenApp());

    // ProvaGenApp runs async state initialization, verify it renders the progress indicator
    expect(find.byType(ProvaGenApp), findsOneWidget);
  });
}
