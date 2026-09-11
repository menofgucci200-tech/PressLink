import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../theme/app_colors.dart';
import '../../features/home/presentation/home_screen.dart';
import '../../features/notifications/presentation/notifications_controller.dart';
import '../../features/notifications/presentation/notifications_screen.dart';
import '../../features/orders/presentation/orders_controller.dart';
import '../../features/orders/presentation/orders_list_screen.dart';
import 'main_menu_sheet.dart';

/// Coquille de navigation principale — bottom nav 3 onglets + menu
/// (Architecture des écrans §3 revue le 26/08) : Accueil · Commandes ·
/// Notifications · Menu (Profil, Signaler un problème, futurs menus).
class MainShell extends ConsumerStatefulWidget {
  const MainShell({super.key});

  @override
  ConsumerState<MainShell> createState() => _MainShellState();
}

class _MainShellState extends ConsumerState<MainShell>
    with WidgetsBindingObserver {
  int _index = 0;

  static const _screens = [
    HomeScreen(),
    OrdersListScreen(),
    NotificationsScreen(),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  /// Les 3 onglets sont construits une seule fois et gardés vivants en
  /// permanence (`IndexedStack`) pour préserver leur état de scroll — mais
  /// ça veut dire que rien ne les recrée jamais pour aller chercher des
  /// données fraîches. On invalide donc explicitement à deux moments où
  /// des changements ont pu arriver sans que l'app le sache : le retour au
  /// premier plan (une commande a pu changer de statut pendant que l'app
  /// était en arrière-plan) et chaque changement d'onglet.
  void _invalidateAll() {
    ref.invalidate(ordersProvider);
    ref.invalidate(notificationsProvider);
    ref.invalidate(unreadNotificationsCountProvider);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _invalidateAll();
  }

  void _onTap(int index) {
    if (index == 3) {
      showMainMenuSheet(context);
      return;
    }
    setState(() => _index = index);
    _invalidateAll();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // `IndexedStack` (pas de rebuild des onglets cachés, cf. commentaire
      // plus haut) : on ne peut donc pas faire un crossfade classique entre
      // écrans sans les reconstruire. La touche de fluidité vient plutôt
      // d'une transition douce sur la barre elle-même (icône/label animés).
      body: IndexedStack(index: _index, children: _screens),
      bottomNavigationBar: _AnimatedBottomNav(index: _index, onTap: _onTap),
    );
  }
}

class _AnimatedBottomNav extends StatelessWidget {
  const _AnimatedBottomNav({required this.index, required this.onTap});

  final int index;
  final ValueChanged<int> onTap;

  static const _items = [
    (icon: Icons.home_outlined, activeIcon: Icons.home, label: 'Accueil'),
    (
      icon: Icons.inventory_2_outlined,
      activeIcon: Icons.inventory_2,
      label: 'Commandes',
    ),
    (
      icon: Icons.notifications_outlined,
      activeIcon: Icons.notifications,
      label: 'Notifs',
    ),
    (icon: Icons.menu, activeIcon: Icons.menu, label: 'Menu'),
  ];

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return BottomAppBar(
      padding: EdgeInsets.zero,
      child: SizedBox(
        height: 60,
        child: Row(
          children: [
            for (var i = 0; i < _items.length; i++)
              Expanded(
                child: InkWell(
                  onTap: () => onTap(i),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      TweenAnimationBuilder<double>(
                        duration: const Duration(milliseconds: 220),
                        curve: Curves.easeOutBack,
                        tween: Tween(begin: 1, end: i == index ? 1.12 : 1.0),
                        builder: (context, scale, child) =>
                            Transform.scale(scale: scale, child: child),
                        child: Icon(
                          i == index ? _items[i].activeIcon : _items[i].icon,
                          color: i == index
                              ? AppColors.primary
                              : theme.textTheme.bodyMedium?.color,
                          size: 24,
                        ),
                      ),
                      const SizedBox(height: 3),
                      AnimatedDefaultTextStyle(
                        duration: const Duration(milliseconds: 200),
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: i == index
                              ? FontWeight.w600
                              : FontWeight.w400,
                          color: i == index
                              ? AppColors.primary
                              : theme.textTheme.bodyMedium?.color,
                        ),
                        child: Text(_items[i].label),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
