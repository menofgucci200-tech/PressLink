import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../theme/app_colors.dart';
import '../../features/home/presentation/home_screen.dart';
import '../../features/notifications/presentation/notifications_controller.dart';
import '../../features/orders/presentation/orders_controller.dart';
import '../../features/orders/presentation/orders_list_screen.dart';
import '../../features/pressings/presentation/join_pressing_screen.dart';
import '../../features/support/presentation/support_screen.dart';
import 'app_page_route.dart';
import 'main_menu_fan.dart';
import 'presslink_top_bar.dart';

/// Coquille de navigation principale — barre « onglet découpé » à 5
/// emplacements : Accueil · Commandes · + (rejoindre un pressing) · Support
/// · Menu (bulles en éventail). Les notifications restent sur la cloche de
/// l'accueil.
class MainShell extends ConsumerStatefulWidget {
  const MainShell({super.key});

  @override
  ConsumerState<MainShell> createState() => _MainShellState();
}

class _MainShellState extends ConsumerState<MainShell>
    with WidgetsBindingObserver, SingleTickerProviderStateMixin {
  late final AnimationController _menu = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 420),
    reverseDuration: const Duration(milliseconds: 260),
  );

  bool get _menuOpen =>
      _menu.status == AnimationStatus.forward ||
      _menu.status == AnimationStatus.completed;

  static const _screens = [HomeScreen(), OrdersListScreen(), SupportScreen()];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _menu.dispose();
    super.dispose();
  }

  void _openMenu() {
    setState(() => _menu.forward());
  }

  void _closeMenu() {
    if (!_menuOpen) return;
    setState(() => _menu.reverse());
  }

  /// Les onglets sont construits une seule fois et gardés vivants en
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

  void _onTabSelected(int index) {
    _closeMenu();
    ref.read(mainTabIndexProvider.notifier).state = index;
  }

  @override
  Widget build(BuildContext context) {
    // Onglet partagé avec la barre du haut (sa flèche ramène à l'Accueil).
    final index = ref.watch(mainTabIndexProvider);
    ref.listen(mainTabIndexProvider, (previous, next) => _invalidateAll());

    // Retour (bouton Android, retour du navigateur) : referme d'abord le
    // menu, puis ramène à l'Accueil depuis les autres onglets.
    return PopScope(
      canPop: !_menuOpen && index == 0,
      onPopInvokedWithResult: (didPop, _) {
        if (didPop) return;
        if (_menuOpen) {
          _closeMenu();
        } else {
          ref.read(mainTabIndexProvider.notifier).state = 0;
        }
      },
      child: Scaffold(
        // `IndexedStack` (pas de rebuild des onglets cachés, cf. commentaire
        // plus haut) : on ne peut donc pas faire un crossfade classique entre
        // écrans sans les reconstruire. La touche de fluidité vient plutôt de
        // la barre elle-même (bulle qui glisse, encoche qui la suit).
        body: Stack(
          children: [
            IndexedStack(index: index, children: _screens),
            Positioned.fill(
              child: MainMenuFan(animation: _menu, onClose: _closeMenu),
            ),
          ],
        ),
        bottomNavigationBar: NotchedBottomNav(
          index: index,
          menuOpen: _menuOpen,
          menuAnimation: _menu,
          onTabSelected: _onTabSelected,
          onAdd: () {
            _closeMenu();
            Navigator.of(
              context,
            ).push(AppPageRoute(builder: (_) => const JoinPressingScreen()));
          },
          onMenu: () => _menuOpen ? _closeMenu() : _openMenu(),
        ),
      ),
    );
  }
}

/// Barre de navigation « onglet découpé » (même bleu que la sidebar des
/// dashboards) : l'onglet actif sort de la barre dans une bulle posée dans
/// une encoche circulaire ; le « + » central garde en permanence la sienne.
class NotchedBottomNav extends StatelessWidget {
  const NotchedBottomNav({
    super.key,
    required this.index,
    required this.onTabSelected,
    required this.onAdd,
    required this.onMenu,
    this.menuOpen = false,
    this.menuAnimation,
  });

  /// Onglet actif : 0 Accueil, 1 Commandes, 2 Support.
  final int index;

  /// Menu ouvert : la bulle glisse sur « Menu » et devient une croix.
  final bool menuOpen;

  /// Ouverture du menu (0 → 1) : voile assorti à celui de l'écran, visible
  /// dans les encoches.
  final Animation<double>? menuAnimation;
  final ValueChanged<int> onTabSelected;
  final VoidCallback onAdd;
  final VoidCallback onMenu;

  static const bubbleRadius = 24.0;
  static const barHeight = 58.0;
  static const _addSlot = 2;

  /// Emplacement (0 à 4) de chaque onglet ; le 2 est le « + », le 4 le menu.
  static const _tabSlots = [0, 1, 3];

  static const _slots = [
    (icon: Icons.home_outlined, activeIcon: Icons.home, label: 'Accueil'),
    (
      icon: Icons.inventory_2_outlined,
      activeIcon: Icons.inventory_2,
      label: 'Commandes',
    ),
    (icon: Icons.add, activeIcon: Icons.add, label: 'Ajouter'),
    (
      icon: Icons.headset_mic_outlined,
      activeIcon: Icons.headset_mic,
      label: 'Support',
    ),
    (icon: Icons.menu, activeIcon: Icons.menu, label: 'Menu'),
  ];

  void _onSlotTap(int slot) {
    if (slot == _addSlot) {
      onAdd();
    } else if (slot == 4) {
      onMenu();
    } else {
      onTabSelected(_tabSlots.indexOf(slot));
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bottomInset = MediaQuery.of(context).viewPadding.bottom;
    final activeSlot = menuOpen ? 4 : _tabSlots[index];

    return SizedBox(
      height: bubbleRadius + barHeight + bottomInset,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final slotWidth = constraints.maxWidth / _slots.length;
          double centerOf(int slot) => (slot + 0.5) * slotWidth;

          return TweenAnimationBuilder<double>(
            tween: Tween(end: centerOf(activeSlot)),
            duration: const Duration(milliseconds: 260),
            curve: Curves.easeOutCubic,
            builder: (context, activeX, _) {
              final plusX = centerOf(_addSlot);
              return Material(
                type: MaterialType.transparency,
                child: Stack(
                  clipBehavior: Clip.none,
                  children: [
                    if (menuAnimation != null)
                      Positioned.fill(
                        child: IgnorePointer(
                          child: AnimatedBuilder(
                            animation: menuAnimation!,
                            builder: (context, _) => ColoredBox(
                              color: menuDimColor(isDark).withValues(
                                alpha:
                                    menuDimColor(isDark).a *
                                    menuAnimation!.value,
                              ),
                            ),
                          ),
                        ),
                      ),
                    Positioned.fill(
                      child: CustomPaint(
                        painter: _NotchedBarPainter(
                          notches: [activeX, plusX],
                          top: bubbleRadius,
                          notchRadius: bubbleRadius + 6,
                        ),
                      ),
                    ),
                    Positioned(
                      left: 0,
                      right: 0,
                      top: bubbleRadius,
                      height: barHeight,
                      child: Row(
                        children: [
                          for (var slot = 0; slot < _slots.length; slot++)
                            Expanded(
                              child: _SlotButton(
                                icon: _slots[slot].icon,
                                label: slot == 4 && menuOpen
                                    ? 'Fermer'
                                    : _slots[slot].label,
                                selected: slot == activeSlot,
                                emphasized:
                                    slot == activeSlot || slot == _addSlot,
                                // L'icône s'efface sous la bulle qui passe
                                // (et sous le « + », qui a sa propre bulle).
                                iconOpacity: slot == _addSlot
                                    ? 0
                                    : (((centerOf(slot) - activeX).abs() - 16) /
                                              40)
                                          .clamp(0.0, 1.0),
                                onTap: () => _onSlotTap(slot),
                              ),
                            ),
                        ],
                      ),
                    ),
                    Positioned(
                      left: plusX - bubbleRadius,
                      top: 0,
                      // Toute la bulle du « + » est touchable, y compris
                      // la moitié qui dépasse au-dessus de la barre.
                      child: ExcludeSemantics(
                        child: GestureDetector(
                          behavior: HitTestBehavior.opaque,
                          onTap: onAdd,
                          child: _Bubble(
                            isDark: isDark,
                            plus: true,
                            icon: Icons.add,
                          ),
                        ),
                      ),
                    ),
                    Positioned(
                      left: activeX - bubbleRadius,
                      top: 0,
                      // Touchable en entier (ex. la croix du menu ouvert).
                      child: ExcludeSemantics(
                        child: GestureDetector(
                          behavior: HitTestBehavior.opaque,
                          onTap: () => _onSlotTap(activeSlot),
                          child: _Bubble(
                            isDark: isDark,
                            icon: menuOpen
                                ? Icons.close
                                : _slots[activeSlot].activeIcon,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _SlotButton extends StatelessWidget {
  const _SlotButton({
    required this.icon,
    required this.label,
    required this.selected,
    required this.emphasized,
    required this.iconOpacity,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final bool emphasized;
  final double iconOpacity;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = emphasized ? Colors.white : const Color(0xD1DBEAFE);
    return Semantics(
      button: true,
      selected: selected,
      label: label,
      excludeSemantics: true,
      child: InkResponse(
        onTap: onTap,
        radius: 34,
        highlightShape: BoxShape.circle,
        splashColor: Colors.white.withValues(alpha: 0.12),
        highlightColor: Colors.white.withValues(alpha: 0.06),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Opacity(
              opacity: iconOpacity,
              child: Icon(icon, size: 24, color: color),
            ),
            const SizedBox(height: 3),
            AnimatedDefaultTextStyle(
              duration: const Duration(milliseconds: 200),
              style: TextStyle(
                fontSize: 11,
                fontWeight: emphasized ? FontWeight.w700 : FontWeight.w500,
                color: color,
              ),
              child: Text(label, maxLines: 1, overflow: TextOverflow.clip),
            ),
          ],
        ),
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.isDark, required this.icon, this.plus = false});

  final bool isDark;
  final IconData icon;
  final bool plus;

  @override
  Widget build(BuildContext context) {
    const size = NotchedBottomNav.bubbleRadius * 2;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: plus
            ? const LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [Color(0xFF22D3EE), AppColors.primary],
                stops: [0, 0.7],
              )
            : null,
        color: plus ? null : (isDark ? const Color(0xFF16233D) : Colors.white),
        border: !plus && isDark
            ? Border.all(color: const Color(0xFF2D3F5F))
            : null,
        boxShadow: [
          BoxShadow(
            color: plus
                ? AppColors.secondary.withValues(alpha: 0.55)
                : Colors.black.withValues(alpha: isDark ? 0.5 : 0.25),
            blurRadius: 16,
            offset: const Offset(0, 8),
            spreadRadius: -6,
          ),
        ],
      ),
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 180),
        child: Icon(
          icon,
          key: ValueKey(icon),
          size: plus ? 28 : 24,
          color: plus
              ? Colors.white
              : (isDark ? const Color(0xFF93C5FD) : AppColors.primary),
        ),
      ),
    );
  }
}

/// Fond de la barre : dégradé bleu PressLink, creusé sous chaque bulle d'une
/// encoche circulaire (demi-cercle concentrique à la bulle, raccordé au bord
/// par deux petits arrondis). Deux encoches proches se combinent.
class _NotchedBarPainter extends CustomPainter {
  const _NotchedBarPainter({
    required this.notches,
    required this.top,
    required this.notchRadius,
  });

  final List<double> notches;
  final double top;
  final double notchRadius;

  static const _fillet = 8.0;

  double _depthAt(double dx) {
    final r = notchRadius;
    const f = _fillet;
    final xf = math.sqrt((r + f) * (r + f) - f * f);
    final xp = r * xf / (r + f);
    final a = dx.abs();
    if (a <= xp) return math.sqrt(r * r - a * a);
    if (a <= xf) return f - math.sqrt(math.max(0, f * f - (xf - a) * (xf - a)));
    return 0;
  }

  @override
  void paint(Canvas canvas, Size size) {
    double edge(double x) =>
        top + notches.map((c) => _depthAt(x - c)).reduce(math.max);

    final path = Path()..moveTo(0, edge(0));
    for (var x = 0.5; x <= size.width; x += 0.5) {
      path.lineTo(x, edge(x));
    }
    path
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();

    final paint = Paint()
      ..shader = const LinearGradient(
        colors: [Color(0xFF1E3A8A), Color(0xFF1E40AF), AppColors.primary],
        stops: [0, 0.55, 1],
      ).createShader(Offset.zero & size);
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(_NotchedBarPainter oldDelegate) =>
      oldDelegate.top != top ||
      oldDelegate.notchRadius != notchRadius ||
      oldDelegate.notches.length != notches.length ||
      [
        for (var i = 0; i < notches.length; i++)
          oldDelegate.notches[i] != notches[i],
      ].any((changed) => changed);
}
