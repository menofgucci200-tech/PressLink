import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:presslink_app/core/theme/app_theme.dart';
import 'package:presslink_app/core/widgets/app_back_button.dart';
import 'package:presslink_app/core/widgets/main_shell.dart';
import 'package:presslink_app/features/support/presentation/support_screen.dart';

void main() {
  Future<List<String>> pumpNav(WidgetTester tester, {int index = 0}) async {
    final calls = <String>[];
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: Scaffold(
          body: const SizedBox.expand(),
          bottomNavigationBar: NotchedBottomNav(
            index: index,
            onTabSelected: (i) => calls.add('tab:$i'),
            onAdd: () => calls.add('add'),
            onMenu: () => calls.add('menu'),
          ),
        ),
      ),
    );
    return calls;
  }

  testWidgets('five slots, support replaces notifications', (tester) async {
    await pumpNav(tester);

    for (final label in [
      'Accueil',
      'Commandes',
      'Ajouter',
      'Support',
      'Menu',
    ]) {
      expect(find.text(label), findsOneWidget);
    }
    expect(find.text('Notifs'), findsNothing);
  });

  testWidgets('each slot triggers its own action', (tester) async {
    final calls = await pumpNav(tester);

    await tester.tap(find.text('Commandes'));
    await tester.tap(find.text('Support'));
    await tester.tap(find.text('Ajouter'));
    await tester.tap(find.text('Menu'));
    await tester.pump();

    expect(calls, ['tab:1', 'tab:2', 'add', 'menu']);
  });

  testWidgets('the raised plus bubble is tappable above the bar', (
    tester,
  ) async {
    final calls = await pumpNav(tester, index: 2);

    final plus = tester.getCenter(find.byIcon(Icons.add).last);
    await tester.tapAt(plus.translate(0, -14));
    await tester.pump();

    expect(calls, ['add']);
  });

  testWidgets('support has no back button as a tab, even under another page', (
    tester,
  ) async {
    final theme = ValueNotifier(AppTheme.light);
    await tester.pumpWidget(
      ProviderScope(
        child: ValueListenableBuilder<ThemeData>(
          valueListenable: theme,
          builder: (context, value, _) =>
              MaterialApp(theme: value, home: const SupportScreen()),
        ),
      ),
    );
    await tester.pump();
    expect(find.byType(AppBackButton), findsNothing);

    final navigator = tester.state<NavigatorState>(find.byType(Navigator));
    navigator.push(MaterialPageRoute(builder: (_) => const SupportScreen()));
    await tester.pumpAndSettle();
    // Passage en mode nuit pendant que la page est ouverte : l'onglet du
    // dessous est reconstruit, sans gagner de bouton retour pour autant.
    theme.value = AppTheme.dark;
    await tester.pumpAndSettle();
    // La page ouverte par-dessus a son bouton retour ; l'onglet en dessous non.
    expect(find.byType(AppBackButton, skipOffstage: false), findsOneWidget);
  });
}
