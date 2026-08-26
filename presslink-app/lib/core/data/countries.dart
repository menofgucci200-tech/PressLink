class Country {
  const Country({required this.name, required this.flag, required this.dialCode});

  final String name;
  final String flag;
  final String dialCode;
}

/// Pays proposés dans le sélecteur d'indicatif — Afrique en priorité
/// (marché cible PressLink), puis les autres marchés courants.
const List<Country> kCountries = [
  Country(name: "Côte d'Ivoire", flag: '🇨🇮', dialCode: '+225'),
  Country(name: 'Tanzanie', flag: '🇹🇿', dialCode: '+255'),
  Country(name: 'Sénégal', flag: '🇸🇳', dialCode: '+221'),
  Country(name: 'Bénin', flag: '🇧🇯', dialCode: '+229'),
  Country(name: 'Togo', flag: '🇹🇬', dialCode: '+228'),
  Country(name: 'Cameroun', flag: '🇨🇲', dialCode: '+237'),
  Country(name: 'Guinée', flag: '🇬🇳', dialCode: '+224'),
  Country(name: 'Mali', flag: '🇲🇱', dialCode: '+223'),
  Country(name: 'Burkina Faso', flag: '🇧🇫', dialCode: '+226'),
  Country(name: 'Ghana', flag: '🇬🇭', dialCode: '+233'),
  Country(name: 'Nigéria', flag: '🇳🇬', dialCode: '+234'),
  Country(name: 'Kenya', flag: '🇰🇪', dialCode: '+254'),
  Country(name: 'Ouganda', flag: '🇺🇬', dialCode: '+256'),
  Country(name: 'Rwanda', flag: '🇷🇼', dialCode: '+250'),
  Country(name: 'RD Congo', flag: '🇨🇩', dialCode: '+243'),
  Country(name: 'Maroc', flag: '🇲🇦', dialCode: '+212'),
  Country(name: 'Algérie', flag: '🇩🇿', dialCode: '+213'),
  Country(name: 'Tunisie', flag: '🇹🇳', dialCode: '+216'),
  Country(name: 'France', flag: '🇫🇷', dialCode: '+33'),
  Country(name: 'Belgique', flag: '🇧🇪', dialCode: '+32'),
  Country(name: 'Suisse', flag: '🇨🇭', dialCode: '+41'),
  Country(name: 'Canada', flag: '🇨🇦', dialCode: '+1'),
  Country(name: 'États-Unis', flag: '🇺🇸', dialCode: '+1'),
  Country(name: 'Royaume-Uni', flag: '🇬🇧', dialCode: '+44'),
];
