# encoding: utf-8
"""Bangun bundle plugin Glyphs dari src/.

  python3 src/build.py

Hasil (di folder VariableStudio/):
  Variable Studio.glyphsPlugin      jendela (Window > Variable Studio)
  Variable Studio.glyphsReporter    pratinjau (View > Show Variable Studio)

plugin.py tiap bundle = gabungan modul src (baris bertanda BUILD:STRIP
dibuang karena semua modul jadi satu file)."""
import os
import plistlib
import shutil
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
sys.path.insert(0, HERE)
from vs_core import VS_VERSION  # noqa: E402

BUNDLES = [
	("Variable Studio.glyphsPlugin", "VariableStudio", "com.satuyasa.VariableStudio",
		["vs_core.py", "vs_glyphs.py", "vs_window.py"]),
	("Variable Studio.glyphsReporter", "VariableStudioReporter", "com.satuyasa.VariableStudioReporter",
		["vs_core.py", "vs_glyphs.py", "vs_reporter.py"]),
]


def combine(files):
	out = ["# encoding: utf-8", "# DIBUAT OLEH src/build.py - jangan edit di sini, edit src/*.py", ""]
	for name in files:
		out.append("# " + "=" * 70)
		out.append("# " + name)
		for line in open(os.path.join(HERE, name), encoding="utf-8").read().splitlines():
			if "BUILD:STRIP" in line or line.startswith("# encoding") or line.startswith("from __future__"):
				continue
			out.append(line)
	return "\n".join(out) + "\n"


def main():
	loader = os.path.join(HERE, "loader", "plugin")
	for bundle, cls, ident, files in BUNDLES:
		path = os.path.join(ROOT, bundle)
		if os.path.exists(path):
			shutil.rmtree(path)
		os.makedirs(os.path.join(path, "Contents", "Resources"))
		code = combine(files)
		compile(code, bundle, "exec")
		with open(os.path.join(path, "Contents", "Resources", "plugin.py"), "w", encoding="utf-8") as f:
			f.write(code)
		info = {
			"CFBundleDevelopmentRegion": "en",
			"CFBundleExecutable": "plugin",
			"CFBundleIdentifier": ident,
			"CFBundleInfoDictionaryVersion": "6.0",
			"CFBundleName": bundle.split(".")[0],
			"CFBundlePackageType": "BNDL",
			"CFBundleShortVersionString": VS_VERSION,
			"CFBundleVersion": VS_VERSION.replace(".", ""),
			"NSHumanReadableCopyright": "Copyright, satuyasa",
			"NSPrincipalClass": cls,
			"PyMainFileNames": ["plugin.py"],
			"productPageURL": "https://github.com/satuyasa/satuyasa",
		}
		with open(os.path.join(path, "Contents", "Info.plist"), "wb") as f:
			plistlib.dump(info, f)
		if os.path.exists(loader):
			os.makedirs(os.path.join(path, "Contents", "MacOS"))
			shutil.copy2(loader, os.path.join(path, "Contents", "MacOS", "plugin"))
		print("dibuat:", bundle)


if __name__ == "__main__":
	main()
