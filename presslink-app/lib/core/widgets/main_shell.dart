import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

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

class _MainShellState extends ConsumerState<MainShell> with WidgetsBindingObserver {
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
      body: IndexedStack(index: _index, children: _screens),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _index,
        onTap: _onTap,
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.home_outlined), activeIcon: Icon(Icons.home), label: 'Accueil'),
          BottomNavigationBarItem(icon: Icon(Icons.inventory_2_outlined), activeIcon: Icon(Icons.inventory_2), label: 'Commandes'),
          BottomNavigationBarItem(icon: Icon(Icons.notifications_outlined), activeIcon: Icon(Icons.notifications), label: 'Notifs'),
          BottomNavigationBarItem(icon: Icon(Icons.menu), label: 'Menu'),
        ],
      ),
    );
  }
}
