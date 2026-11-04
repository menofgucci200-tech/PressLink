// Chrome arrondit la taille de page au 1/100 de pouce : on remet la MediaBox au format exact
// (le contenu est calé en haut à gauche, l'excédent est en bas et à droite) et on ajoute
// TrimBox (format fini) et BleedBox (fond perdu), lues par les logiciels d'imprimerie.
//   swift boites-pdf.swift fichier.pdf largeurFinieMm hauteurFinieMm fondPerduMm margeMm
import PDFKit
let a = CommandLine.arguments
let url = URL(fileURLWithPath: a[1])
let doc = PDFDocument(url: url)!
let pt = { (mm: Double) -> CGFloat in CGFloat(mm / 25.4 * 72) }
let W = Double(a[2])!, H = Double(a[3])!, B = Double(a[4])!, S = Double(a[5])!
for i in 0..<doc.pageCount {
    let p = doc.page(at: i)!
    let m = p.bounds(for: .mediaBox)
    let PW = pt(W + 2 * S), PH = pt(H + 2 * S)
    let y0 = m.maxY - PH
    let media = CGRect(x: m.minX, y: y0, width: PW, height: PH)
    p.setBounds(media, for: .mediaBox)
    p.setBounds(media, for: .cropBox)
    p.setBounds(CGRect(x: m.minX + pt(S - B), y: y0 + pt(S - B), width: pt(W + 2 * B), height: pt(H + 2 * B)), for: .bleedBox)
    p.setBounds(CGRect(x: m.minX + pt(S), y: y0 + pt(S), width: pt(W), height: pt(H)), for: .trimBox)
}
let tmp = url.deletingLastPathComponent().appendingPathComponent(".tmp-" + url.lastPathComponent)
guard doc.write(to: tmp) else { fatalError("écriture impossible") }
try! FileManager.default.removeItem(at: url); try! FileManager.default.moveItem(at: tmp, to: url)
print(String(format: "%@ : %d page(s), %.2f × %.2f mm, format fini %.0f × %.0f mm", url.lastPathComponent, doc.pageCount, W + 2 * S, H + 2 * S, W, H))
