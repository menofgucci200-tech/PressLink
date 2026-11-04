// Rastérise chaque page d'un PDF en PNG (PDFKit) et affiche la taille de page en mm.
//   swift pdf2png.swift fichier.pdf dossier-sortie dpi
import PDFKit; import AppKit
let a = CommandLine.arguments
let doc = PDFDocument(url: URL(fileURLWithPath: a[1]))!
let dpi = CGFloat(Double(a[3]) ?? 300)
let base = (a[1] as NSString).lastPathComponent.replacingOccurrences(of: ".pdf", with: "")
for i in 0..<doc.pageCount {
    let page = doc.page(at: i)!
    let box = page.bounds(for: .mediaBox)
    let w = Int(box.width / 72 * dpi), h = Int(box.height / 72 * dpi)
    let cs = CGColorSpaceCreateDeviceRGB()
    let ctx = CGContext(data: nil, width: w, height: h, bitsPerComponent: 8, bytesPerRow: 0, space: cs, bitmapInfo: CGImageAlphaInfo.premultipliedLast.rawValue)!
    ctx.setFillColor(CGColor(red: 1, green: 1, blue: 1, alpha: 1)); ctx.fill(CGRect(x: 0, y: 0, width: w, height: h))
    ctx.scaleBy(x: dpi / 72, y: dpi / 72)
    page.draw(with: .mediaBox, to: ctx)
    let rep = NSBitmapImageRep(cgImage: ctx.makeImage()!)
    let out = "\(a[2])/\(base)-p\(i + 1).png"
    try! rep.representation(using: .png, properties: [:])!.write(to: URL(fileURLWithPath: out))
    print(String(format: "%@ page %d : %.1f × %.1f mm → %@", base, i + 1, box.width / 72 * 25.4, box.height / 72 * 25.4, out))
}
