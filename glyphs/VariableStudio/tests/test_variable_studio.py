# encoding: utf-8
"""Tes Variable Studio dengan Glyphs tiruan:  python3 tests/test_variable_studio.py"""
import importlib.util
import math
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
sys.path.insert(0, os.path.join(HERE, "mock"))

import GlyphsApp  # noqa: E402
from GlyphsApp import (GSFont, GSFontMaster, GSAxis, GSGlyph, GSLayer, GSPath, GSNode,  # noqa: E402
	GSAnchor, GSComponent, Glyphs)


def load(bundle):
	path = os.path.join(ROOT, bundle, "Contents", "Resources", "plugin.py")
	spec = importlib.util.spec_from_file_location(bundle.split(".")[1], path)
	mod = importlib.util.module_from_spec(spec)
	spec.loader.exec_module(mod)
	return mod


P = load("Variable Studio.glyphsPlugin")
R = load("Variable Studio.glyphsReporter")


def oval(cx, cy, rx, ry, k=0.5523):
	"""Elips 4 kurva, gaya Glyphs (node terakhir = titik awal)."""
	pts = [
		((cx + rx, cy + ry * k), "offcurve"), ((cx + rx * k, cy + ry), "offcurve"), ((cx, cy + ry), "curve"),
		((cx - rx * k, cy + ry), "offcurve"), ((cx - rx, cy + ry * k), "offcurve"), ((cx - rx, cy), "curve"),
		((cx - rx, cy - ry * k), "offcurve"), ((cx - rx * k, cy - ry), "offcurve"), ((cx, cy - ry), "curve"),
		((cx + rx * k, cy - ry), "offcurve"), ((cx + rx, cy - ry * k), "offcurve"), ((cx + rx, cy), "curve"),
	]
	return GSPath([GSNode(p, t) for p, t in pts])


def rect(x0, y0, x1, y1):
	return GSPath([GSNode(p, "line") for p in [(x0, y0), (x1, y0), (x1, y1), (x0, y1)]])


def make_font():
	f = GSFont()
	ax = GSAxis()
	ax.name, ax.axisTag = "Weight", "wght"
	f.axes.append(ax)
	for name in ("o", "H", "oacute", "acutecomb"):
		f.glyphs.append(GSGlyph(name))
	light, bold = GSFontMaster("Light"), GSFontMaster("Bold")
	for m, v in ((light, 40), (bold, 160)):
		f.masters.append(m)
		m.axes = [v]
	for m, s in ((light, 20), (bold, 80)):
		g = f.glyphs["o"].layers[m.id]
		g.paths = [oval(300, 250, 250, 260), oval(300, 250, 250 - s, 260 - s * 0.8)]
		g.paths[1].reverse()
		g.anchors = [GSAnchor("top", (300, 520))]
		g.width = 600 + s
		h = f.glyphs["H"].layers[m.id]
		h.paths = [rect(50, 0, 50 + s, 700), rect(450 - s, 0, 450, 700), rect(50, 330, 450, 330 + s / 2)]
		a = f.glyphs["acutecomb"].layers[m.id]
		a.paths = [rect(-30, 550, 30 + s / 4, 650)]
		c = f.glyphs["oacute"].layers[m.id]
		c.components = [GSComponent("o"), GSComponent("acutecomb", (300, 0))]
		c.width = 600 + s
	f.kerning[light.id] = {"H": {"o": -10}}
	f.kerning[bold.id] = {"H": {"o": -30}}
	Glyphs.font = f
	return f


def test_core():
	assert P.parse_style_list("Thin=100\n# x\nBold : 700") == [("Thin", 100.0), ("Bold", 700.0)]
	assert P.piecewise(250, [(100, 20), (400, 80)]) == 50
	g = P.design_values([100, 900], 20, 200, "geometric")
	assert abs(g[0] - 20) < 1e-9 and abs(g[1] - 200) < 1e-9
	plan = P.build_instance_plan(P.WEIGHT_PRESETS, None, True, True)
	names = [p["name"] for p in plan]
	assert names[:4] == ["Thin", "Thin Italic", "ExtraLight", "ExtraLight Italic"]
	bi = [p for p in plan if p["name"] == "Bold Italic"][0]
	assert bi["isBold"] and bi["isItalic"] and bi["linkStyle"] == "Regular"
	it = [p for p in plan if p["name"] == "Italic"][0]
	assert it["linkStyle"] == "Regular" and not it["isBold"]
	m = P.slant_transform(10, 250)
	x, y = P.apply_affine(m, 100, 250)
	assert abs(x - 100) < 1e-9
	x, y = P.apply_affine(m, 100, 350)
	assert abs(x - (100 + 100 * math.tan(math.radians(10)))) < 1e-9


def test_points_and_check():
	f = make_font()
	rows = P.read_layer_points(f.glyphs["o"].layers[0])
	assert rows[0]["id"] == "0:0" and rows[-1]["id"] == "1:11" and len(rows) == 24
	csv = P.points_to_csv([rows, rows], ["Light", "Bold"])
	assert csv.splitlines()[0].startswith('"id"') and len(csv.splitlines()) == 25
	assert P.scan_font(f) == []
	# geser titik awal + balik arah di master Bold
	lay = f.glyphs["o"].layers[f.masters[1].id]
	GlyphsApp.MAKE_FIRST_MODE[0] = "first"
	lay.paths[0].nodes[2].makeNodeFirst()
	lay.paths[1].reverse()
	res = dict(P.scan_font(f))
	assert "o" in res, res
	# makeNodeFirst() versi lain (node jadi indeks 0) tidak bisa menghasilkan
	# susunan Glyphs; harus gagal dengan aman tanpa merusak path
	before = [(n.type, tuple(n.position)) for n in lay.paths[0].nodes]
	changes = P.fix_glyph(f.glyphs["o"], f)
	assert any("gagal" in c for c in changes), changes
	assert [(n.type, tuple(n.position)) for n in lay.paths[0].nodes] == before
	GlyphsApp.MAKE_FIRST_MODE[0] = "last"
	for k in (5, 8):
		lay.paths[0].nodes[k].makeNodeFirst()
		changes = P.fix_glyph(f.glyphs["o"], f)
		assert changes and not any("gagal" in c for c in changes), changes
		assert P.check_glyph(f.glyphs["o"], f) == [], P.check_glyph(f.glyphs["o"], f)
	ref = f.glyphs["o"].layers[f.masters[0].id].paths[0].nodes
	assert [n.type for n in lay.paths[0].nodes] == [n.type for n in ref]
	assert lay.paths[0].nodes[-1].position.x > 500  # titik awal kembali di kanan
	# simetris: H tetap kompatibel; jumlah path beda dilaporkan
	f.glyphs["H"].layers[f.masters[1].id].paths.pop()
	assert any("jumlah path" in i for i in P.check_glyph(f.glyphs["H"], f))


def test_interpolate():
	f = make_font()
	d0, err = P.interpolate_glyph(f.glyphs["o"], f, [40])
	assert err is None and abs(d0["width"] - 620) < 1e-9
	d1, _ = P.interpolate_glyph(f.glyphs["o"], f, [100])
	assert abs(d1["width"] - 650) < 1e-9
	assert d1["anchors"][0][0] == "top"
	dc, err = P.interpolate_glyph(f.glyphs["oacute"], f, [100])
	assert err is None and len(dc["paths"]) == 3
	# brace layer ikut model
	g = f.glyphs["H"]
	br = g.layers[f.masters[0].id].copy()
	br.isSpecialLayer = True
	br.attributes = {"coordinates": {f.axes[0].axisId: 100}}
	for p in br.paths:
		p.applyTransform((1, 0, 0, 1, 7, 0))
	g.layers["brace1"] = br
	dm, err = P.interpolate_glyph(g, f, [100])
	assert err is None and abs(dm["paths"][0][2][0][0] - 57) < 1e-9, dm["paths"][0][2][0]


def test_axes_and_instances():
	f = make_font()
	P.add_axis(f, "Width", "wdth", 100)
	assert [list(m.axes) for m in f.masters] == [[40, 100], [160, 100]]
	try:
		P.add_axis(f, "Weight", "wght", 1)
		raise AssertionError("harus gagal")
	except ValueError:
		pass
	plan = P.build_instance_plan(P.WEIGHT_PRESETS, None, True, False)
	planned, warn = P.plan_with_design(f, plan, "linear")
	assert abs(planned[0]["design"][0] - 40) < 1e-9 and abs(planned[-1]["design"][0] - 160) < 1e-9
	created, skipped = P.generate_instances(f, planned, axis_mappings=True)
	assert len(created) == 9 and not skipped
	statics = [i for i in f.instances if i.type == 0]
	assert [i.name for i in statics][0] == "Thin" and len(statics) == 9
	reg = [i for i in statics if i.name == "Regular"][0]
	assert abs(list(reg.axes)[0] - 85) < 1e-9 and reg.weightClass == 400
	assert reg.customParameters["Axis Location"] == [{"Axis": "Weight", "Location": 400.0}, {"Axis": "Width", "Location": 100.0}]
	assert "Weight" in reg.customParameters.all("Elidable STAT Axis Value Name")
	bold = [i for i in statics if i.name == "Bold"][0]
	assert bold.isBold and bold.linkStyle == "Regular"
	assert P.axis_location_dict(f.masters[0]) == {"Weight": 100.0, "Width": 100.0}
	assert P.axis_location_dict(f.masters[1]) == {"Weight": 900.0, "Width": 100.0}
	assert f.customParameters["Axis Mappings"]["wght"]["400"] == 85
	assert len([i for i in f.instances if i.type == 1]) == 1
	# ulang: diganti, bukan ditambah
	P.generate_instances(f, planned)
	assert len([i for i in f.instances if i.type == 0]) == 9
	# mode axislocation sekarang memakai Axis Location master
	planned2, _ = P.plan_with_design(f, plan, "axislocation")
	assert abs(planned2[3]["design"][0] - 85) < 1e-9
	# lebar tanpa axis -> galat jelas
	f2 = make_font()
	try:
		P.plan_with_design(f2, P.build_instance_plan([("Regular", 400)], [("Condensed", 75), ("Normal", 100)]), "linear")
		raise AssertionError("harus gagal")
	except ValueError:
		pass


def test_italic():
	f = make_font()
	new, odd = P.make_italic_masters(f, list(f.masters), 12, "xheight", False, "ital")
	assert [m.name for m in f.masters] == ["Light", "Bold", "Light Italic", "Bold Italic"]
	assert f.axes[1].axisTag == "ital"
	assert [list(m.axes) for m in f.masters] == [[40, 0], [160, 0], [40, 1], [160, 1]]
	assert new[0].italicAngle == 12 and len(set(m.id for m in f.masters)) == 4
	t = math.tan(math.radians(12))
	up = f.glyphs["H"].layers[f.masters[0].id].paths[0].nodes[2].position
	it = f.glyphs["H"].layers[new[0].id].paths[0].nodes[2].position
	assert abs(it.x - (up.x + t * (up.y - 250))) < 1e-6 and it.y == up.y
	c = f.glyphs["oacute"].layers[new[0].id].components[1]
	assert abs(c.position.x - 300) < 1e-9
	assert f.kerning[new[0].id]["H"]["o"] == -10
	assert P.scan_font(f) == []
	# instance italic memakai axis ital
	planned, warn = P.plan_with_design(f, P.build_instance_plan([("Light", 300), ("Bold", 700)], None, True, True), "linear")
	bi = [p for p in planned if p["name"] == "Bold Italic"][0]
	assert bi["design"] == [160, 1] and bi["user"]["Italic"] == 1.0
	# slnt + kompensasi
	f = make_font()
	P.make_italic_masters(f, [f.masters[0]], 10, "baseline", True, "slnt")
	assert list(f.masters[2].axes) == [40, -10]
	assert abs(f.glyphs["o"].layers[f.masters[2].id].width - 620 / math.cos(math.radians(10))) < 1e-6


def test_new_master():
	f = make_font()
	nm, bad = P.make_master_between(f, f.masters[0], f.masters[1], 0, 220, "Black")
	assert list(nm.axes) == [220] and not bad
	h = f.glyphs["H"].layers[nm.id]
	assert abs(h.paths[0].nodes[1].position.x - (50 + 110)) < 1e-9
	assert abs(f.glyphs["o"].layers[nm.id].width - 710) < 1e-9
	assert f.kerning[nm.id]["H"]["o"] == -40
	nm2, bad = P.make_master_between(f, f.masters[0], f.masters[1], 0, 10, "Thin")
	assert abs(f.glyphs["H"].layers[nm2.id].paths[0].nodes[1].position.x - 55) < 1e-9
	assert P.scan_font(f) == []
	# glyph tidak kompatibel dilaporkan
	f.glyphs["H"].layers[f.masters[1].id].paths.pop()
	_, bad = P.make_master_between(f, f.masters[0], f.masters[1], 0, 100, "Mid")
	assert bad == ["H"]
	# instance -> master
	f = make_font()
	inst = GlyphsApp.GSInstance()
	inst.name = "Semi"
	f.instances.append(inst)
	inst.axes = [150]
	nm3, missing = P.instance_to_master(f, inst)
	assert nm3.name == "Semi" and list(nm3.axes) == [150] and not missing


def test_window():
	f = make_font()
	f.selectedLayers = [f.glyphs["o"].layers[f.masters[0].id]]
	plugin = P.VariableStudio()
	plugin.settings()
	plugin.start()
	plugin.showWindow_(None)
	w = plugin.controller
	assert not GlyphsApp.MESSAGES, GlyphsApp.MESSAGES
	t0 = w.w.tabs[0]
	assert len(t0.points.items) == 24 and "kompatibel" in t0.glyph.value
	t0.csv.press()
	assert GlyphsApp.MESSAGES == [] and "Light x" in __import__("AppKit").NSPasteboard.text
	t0.check.press()
	t0.scan.press()
	t0.fix.press()
	t0.openTab.press()
	t0.paint.press()
	# tab axis
	t1 = w.w.tabs[1]
	t1.preset.value = 1
	w.on_preset(t1.preset)
	t1.add.press()
	assert f.axes[1].axisTag == "wdth", GlyphsApp.MESSAGES
	t1.masters.selection = [0]
	t1.axisPop.value = 0
	t1.axisVal.value = "45"
	t1.setVal.press()
	assert list(f.masters[0].axes)[0] == 45
	t1.setVal.press()
	t1.origin.press()
	t1.varSetting.press()
	t1.vm.value = "Weight=200"
	t1.vmAdd.press()
	assert f.customParameters["Virtual Master"] == [{"Axis": "Weight", "Location": 200.0}]
	# tab style
	t2 = w.w.tabs[2]
	t2.widths.value = "Condensed=75\nNormal=100"
	t2.p1.press()
	t2.make.press()
	assert not GlyphsApp.MESSAGES, GlyphsApp.MESSAGES
	assert len([i for i in f.instances if i.type == 0]) == 36, len(f.instances)
	# tab italic
	t3 = w.w.tabs[3]
	t3.make.press()
	assert len(f.masters) == 4 and not GlyphsApp.MESSAGES, GlyphsApp.MESSAGES
	# tab master baru
	t4 = w.w.tabs[4]
	t4.a.value, t4.b.value, t4.axis.value = 0, 1, 0
	t4.value.value = "250"
	t4.name.value = "Black"
	t4.make.press()
	assert f.masters[-1].name == "Black" and not GlyphsApp.MESSAGES, GlyphsApp.MESSAGES
	t4.inst.value = 0
	t4.toMaster.press()
	assert not GlyphsApp.MESSAGES, GlyphsApp.MESSAGES
	# pratinjau + animasi
	t5 = w.w.tabs[5]
	t5.on.value = True
	w.on_pref()
	w.refresh_preview_axis()
	t5.play.press()
	import AppKit
	AppKit.NSTimer.last.block(None)
	assert len(P.preview_location()) == len(f.axes)
	t5.play.press()
	t5.plus.press()
	w.on_update()
	w.w.binds["close"](None)
	assert not GlyphsApp.MESSAGES, GlyphsApp.MESSAGES


def test_reporter():
	f = make_font()
	Glyphs.defaults.clear()
	R.pref_set("previewOn", 1)
	R.set_preview_location([100])
	rep = R.VariableStudioReporter()
	rep.settings()
	layer = f.glyphs["o"].layers[f.masters[0].id]
	rep.background(layer)
	rep.foreground(layer)
	texts = [t for t, _ in R.ReporterPlugin.texts]
	assert "0:2" in texts and "1:9" in texts and "0:0" not in texts and "1:11" not in texts
	ops = R.path_segments(["line", "offcurve", "qcurve", "line"], [(0, 0), (5, 10), (10, 0), (10, -5)])
	assert ops[2][0] == "qcurve"
	R.bezier_from_paths([(True, ["line", "offcurve", "qcurve", "line"], [(0, 0), (5, 10), (10, 0), (10, -5)])])


def test_embolden():
	# kotak = stem 100 -> 140, sidebearing tetap, lebar +40
	sq = [(True, ["line"] * 4, [(50, 0), (150, 0), (150, 700), (50, 700)])]
	r = P.embolden(sq, 200, 40, 0, True, [0, 700])
	assert r["pts"][0] == [(50, 0), (190, 0), (190, 700), (50, 700)] and r["width"] == 240
	# arah path terbalik tetap menebal; negatif = menipis
	cw = [(True, ["line"] * 4, [(50, 0), (50, 700), (150, 700), (150, 0)])]
	assert P.embolden(cw, 200, 40, 0, True, [0, 700])["pts"][0][2] == (190, 700)
	assert P.embolden(sq, 200, -40, 0, True, [0, 700])["pts"][0][1] == (110, 0)
	f = make_font()
	light = f.masters[0]
	o = f.glyphs["o"].layers[light.id]
	outer_top = o.paths[0].nodes[2].position.y
	inner = [P._xy(n) for n in o.paths[1].nodes]
	nodes_before = [len(p.nodes) for p in o.paths]
	# Bold dari Light: master baru, kompatibel, axis diisi
	bold, done = P.embolden_master(f, light, 60, 40, None, "Heavy", 0, 100)
	assert done == 4 and list(bold.axes) == [100], (done, list(bold.axes))
	ob = f.glyphs["o"].layers[bold.id]
	assert [len(p.nodes) for p in ob.paths] == nodes_before
	assert abs(ob.paths[0].nodes[2].position.y - outer_top) < 1e-6  # x-height/overshoot dipegang
	assert abs(ob.width - (o.width + 60)) < 1e-6
	# counter menyempit
	ib = [P._xy(n) for n in ob.paths[1].nodes]
	assert (max(x for x, _ in ib) - min(x for x, _ in ib)) < (max(x for x, _ in inner) - min(x for x, _ in inner)) - 50
	h = f.glyphs["H"].layers[bold.id]
	assert abs((h.paths[0].nodes[1].position.x - h.paths[0].nodes[0].position.x) - 80) < 1e-6
	# komposit: lebar ikut glyph dasar, aksen bergeser setengah
	c = f.glyphs["oacute"].layers[bold.id]
	assert abs(c.width - (620 + 60)) < 1e-6 and abs(c.components[1].position.x - 330) < 1e-6
	assert f.kerning[bold.id]["H"]["o"] == -10
	assert P.scan_font(f) == [], P.scan_font(f)
	# di tempat, hanya glyph tertentu
	w0 = f.glyphs["H"].layers[bold.id].width
	P.embolden_master(f, bold, 10, 0, bold, glyphs=[f.glyphs["H"]])
	assert abs(f.glyphs["H"].layers[bold.id].width - (w0 + 10)) < 1e-6
	assert abs(f.glyphs["o"].layers[bold.id].width - (o.width + 60)) < 1e-6
	# hanya titik terpilih: satu stem saja
	lay = f.glyphs["H"].layers[light.id]
	for n in lay.paths[0].nodes:
		n.selected = True
	x2 = lay.paths[1].nodes[0].position.x
	w = lay.width
	P.embolden_layer(lay, 20, 0, selected_only=True)
	assert abs(lay.paths[0].nodes[1].position.x - 80) < 1e-6 and lay.paths[1].nodes[0].position.x == x2 and lay.width == w
	assert P.check_glyph(f.glyphs["H"], f) == []


def test_window_weight():
	f = make_font()
	f.selectedLayers = [f.glyphs["H"].layers[f.masters[0].id]]
	w = P.VariableStudioWindow()
	t = w.w.tabs[6]
	t.p10.press()
	t.m2.press()
	h = f.glyphs["H"].layers[f.masters[0].id]
	assert abs(h.paths[0].nodes[1].position.x - 78) < 1e-6, h.paths[0].nodes[1].position
	t.apply.press()
	t.src.value = 0
	t.target.value = 0
	w.on_weight_target()
	assert t.value.value == "80"
	t.name.value = "Bold 2"
	t.make.press()
	assert f.masters[-1].name == "Bold 2" and list(f.masters[-1].axes) == [80]
	t.target.value = 2
	t.glyphSel.value = True
	t.make.press()
	t.selOnly.value = True
	try:
		t.m10.press()
	finally:
		pass
	assert GlyphsApp.MESSAGES and "Pilih dulu" in GlyphsApp.MESSAGES[-1][1]
	del GlyphsApp.MESSAGES[:]
	w.w.binds["close"](None)


if __name__ == "__main__":
	n = 0
	for name, fn in sorted(globals().items()):
		if name.startswith("test_") and callable(fn):
			GlyphsApp.MESSAGES[:] = []
			fn()
			n += 1
	print("OK: %d tes lulus" % n)
