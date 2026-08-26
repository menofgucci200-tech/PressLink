/// Échelle d'espacement PressLink — base 4/8px.
/// Source : Design System PressLink §18-20.
abstract final class AppSpacing {
  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 16.0;
  static const lg = 24.0;
  static const xl = 32.0;
  static const xxl = 48.0;
  static const xxxl = 64.0;

  const AppSpacing._();
}

/// Rayons de bordure — Design System PressLink §7.
abstract final class AppRadius {
  static const sm = 6.0;
  static const md = 8.0;
  static const lg = 12.0;
  static const xl = 16.0;
  static const pill = 999.0;

  const AppRadius._();
}
