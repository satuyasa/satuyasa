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
	assert any("punya 2 kontur" in i for i in P.check_glyph(f.glyphs["H"], f))


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


def contrast_o(f, master):
	"""o kontras tinggi: sisi 120, atas/bawah 20."""
	g = f.glyphs["o"]
	l = g.layers[master.id]
	l.paths = [oval(300, 250, 280, 260), oval(300, 250, 160, 240)]
	l.paths[1].reverse()
	l.width = 600
	return l


def test_measure_and_contrast():
	f = make_font()
	light = f.masters[0]
	o = contrast_o(f, light)
	m = P.measure_layer(o)
	sm = P.thickness_summary(m)
	assert abs(sm["all"][0] - 20) < 1 and abs(sm["all"][1] - 120) < 2, sm
	assert abs(sm["v"][1] - 120) < 2 and abs(sm["h"][0] - 20) < 1
	# H bertumpuk (palang menimpa stem) tetap terukur benar
	h = f.glyphs["H"].layers[light.id]
	h.paths = [rect(50, 0, 150, 700), rect(420, 0, 520, 700), rect(50, 335, 520, 355)]
	sh = P.thickness_summary(P.measure_layer(h))
	assert abs(sh["all"][0] - 20) < 1e-6 and abs(sh["all"][1] - 100) < 1e-6, sh
	assert P.contrast_factor(10, 24, 100, "keepthin") == 0 and P.contrast_factor(150, 24, 100, "keepthin") == 1
	assert P.contrast_factor(10, 24, 100, "keepthick") == 1 and P.contrast_factor(62, 24, 100, "uniform") == 1
	assert 0 < P.contrast_factor(62, 24, 100, "keepthin") < 1
	# pertahankan yang tipis: atas/bawah o tetap 20, sisi bertambah 80
	top_before = (o.paths[0].nodes[2].position.y, o.paths[1].nodes[3].position.y)
	P.embolden_layer(o, 80, 80, True, [0, 500], False, "keepthin", 24, 100)
	sm2 = P.thickness_summary(P.measure_layer(o))
	assert abs(sm2["all"][0] - 20) < 1, sm2
	assert abs(sm2["v"][1] - 200) < 3, sm2
	assert abs(o.paths[0].nodes[2].position.y - top_before[0]) < 1e-6
	assert abs(o.width - 680) < 1e-6
	# palang H tetap, stem menebal
	P.embolden_layer(h, 40, 40, True, [0, 700], False, "keepthin", 24, 80)
	sh2 = P.thickness_summary(P.measure_layer(h))
	assert abs(sh2["all"][0] - 20) < 1.5 and abs(sh2["all"][1] - 140) < 1e-6, sh2
	# ujung palang yang tersembunyi di dalam stem tidak menembus keluar
	f2 = make_font()
	h2 = f2.glyphs["H"].layers[f2.masters[0].id]
	h2.paths = [rect(40, 0, 130, 700), rect(400, 0, 490, 700), rect(40, 340, 490, 358)]
	P.embolden_layer(h2, 20, 20, True, [0, 700], False, "keepthick", 24, 80)
	bar = [P._xy(n) for n in h2.paths[2].nodes]
	assert min(x for x, _ in bar) >= 40 and max(x for x, _ in bar) <= 490, bar
	assert abs((max(y for _, y in bar) - min(y for _, y in bar)) - 38) < 1e-6, bar
	# pertahankan yang tebal: hanya hairline yang berubah
	f = make_font()
	o = contrast_o(f, f.masters[0])
	P.embolden_layer(o, 20, 20, True, None, False, "keepthick", 24, 100)
	sm3 = P.thickness_summary(P.measure_layer(o))
	assert abs(sm3["all"][0] - 40) < 1.5 and abs(sm3["v"][1] - 120) < 2, sm3
	try:
		P.embolden_layer(o, 20, 20, mode="keepthin")
		raise AssertionError("harus gagal tanpa batas")
	except ValueError:
		pass
	# master: scan + mode kontras, tetap kompatibel
	f = make_font()
	contrast_o(f, f.masters[0])
	contrast_o(f, f.masters[1])
	rows, sm = P.scan_thickness(f, f.masters[0])
	assert sm["thinnest"]["name"] == "H" and abs(sm["thinnest"]["thin"] - 10) < 1e-6 and sm["lo"] < sm["hi"], sm
	assert sm["thickest"]["name"] == "o" and abs(sm["thickest"]["thick"] - 120) < 2
	assert {r["name"] for r in rows} == {"o", "H", "oacute", "acutecomb"}
	nm, done = P.embolden_master(f, f.masters[0], 60, 40, None, "Heavy", 0, 100,
		mode="keepthin", thin_limit=24, thick_limit=100)
	assert done == 3 and P.scan_font(f) == []  # H: semua garisnya di bawah batas tipis, tidak berubah


def test_window_contrast():
	f = make_font()
	contrast_o(f, f.masters[0])
	f.selectedLayers = [f.glyphs["o"].layers[f.masters[0].id]]
	w = P.VariableStudioWindow()
	t = w.w.tabs[6]
	t.mode.value = 1
	w.on_weight_mode()
	assert t.lo.value and t.hi.value and "tertipis" in t.summary.value, t.summary.value
	assert t.results.items and t.results.items[0]["name"]
	t.results.selection = [0]
	w.open_weight_result()
	assert f.tabs
	o = f.glyphs["o"].layers[f.masters[0].id]
	top = o.paths[0].nodes[2].position.y
	t.p10.press()
	assert o.paths[0].nodes[2].position.y == top and not GlyphsApp.MESSAGES, GlyphsApp.MESSAGES
	t.lo.value, t.hi.value = "50", "40"
	t.p2.press()
	assert GlyphsApp.MESSAGES and "lebih besar" in GlyphsApp.MESSAGES[-1][1]
	del GlyphsApp.MESSAGES[:]
	t.lo.value, t.hi.value = "", ""
	t.name.value = "Black"
	t.make.press()
	assert f.masters[-1].name == "Black" and not GlyphsApp.MESSAGES, GlyphsApp.MESSAGES
	# overlay reporter
	R.pref_set("showThickness", 1)
	R.ReporterPlugin.texts[:] = []
	rep = R.VariableStudioReporter()
	rep.foreground(o)
	labels = [x for x, _ in R.ReporterPlugin.texts]
	assert "20" in labels and "130" in labels, labels  # +10 tadi: sisi 120 -> 130, hairline tetap
	R.pref_set("showThickness", 0)
	w.w.binds["close"](None)


def test_curve_audit():
	"""Audit lengkung: elips tetap elips, titik halus tetap halus, puncak
	mangkuk di x-height tidak lepas walau glyph punya telinga & ekor."""
	def pts_of(path):
		return path[2]
	e = oval(0, 0, 200, 300)
	ep = [(True, [n.type for n in e.nodes], [P._xy(n) for n in e.nodes])]
	k = 0.5523
	src = ep[0][2]
	for dx, dy in ((40, 0), (30, 30), (40, 15)):
		r = P.offset_paths(ep, dx, dy)[0]
		# arah handle tetap: handle di sisi kanan tegak, di puncak mendatar
		assert abs(r[0][0] - r[11][0]) < 1e-6 and abs(r[1][1] - r[2][1]) < 1e-6
		# titik tengah lengkung tepat di offset sejati (geser tegak lurus per sumbu)
		ctrl = [src[11], src[0], src[1], src[2]]
		mid, tan = P._bez(ctrl, 0.5), P._bez_tangent(ctrl, 0.5)
		nm = (tan[1], -tan[0])
		want = (mid[0] + nm[0] * dx, mid[1] + nm[1] * dy)
		got = P._bez([r[11], r[0], r[1], r[2]], 0.5)
		assert math.hypot(got[0] - want[0], got[1] - want[1]) < 0.01, (dx, dy, got, want)
	# titik halus diagonal: kedua handle tetap segaris setelah tebal x != y
	diag = [(True, ["curve", "offcurve", "offcurve", "curve", "offcurve", "offcurve", "curve", "line"],
		[(0, 0), (60, 20), (120, 140), (200, 200), (280, 260), (300, 380), (300, 500), (0, 500)])]
	r = P.offset_paths(diag, 40, 10)[0]
	(ax, ay), (bx, by), (cx, cy) = r[2], r[3], r[4]
	assert abs((bx - ax) * (cy - by) - (by - ay) * (cx - bx)) < 1e-6
	# mangkuk g: puncak di x-height (overshoot 510) tetap, goresan atas +40 ke dalam
	f = make_font()
	l = f.glyphs["o"].layers[f.masters[0].id]
	l.paths = [oval(300, 250, 250, 260), oval(300, 250, 190, 230), rect(480, 480, 560, 700), rect(100, -200, 500, -150)]
	l.paths[1].reverse()
	P.embolden_layer(l, 40, 40, True, [0, 500, 700, -200])
	assert abs(l.paths[0].nodes[2].position.y - 510) < 1e-6 and abs(l.paths[0].nodes[8].position.y + 10) < 1e-6
	inner_top = max(n.position.y for n in l.paths[1].nodes if n.type != "offcurve")
	assert abs((510 - inner_top) - 70) < 1e-6, inner_top
	ear_top = max(n.position.y for n in l.paths[2].nodes)
	assert abs(ear_top - 700) < 1e-6
	# bahu n: sambungan stem naik, tapi lengkung tidak boleh menonjol di atas x-height
	types = ["line", "line", "line", "offcurve", "offcurve", "curve", "offcurve", "offcurve", "curve", "line", "line",
		"line", "offcurve", "offcurve", "curve", "offcurve", "offcurve", "curve", "line", "line"]
	pts = [(40, 0), (110, 0), (110, 390), (130, 440), (185, 462), (235, 462), (285, 462), (300, 430), (300, 400),
		(300, 0), (370, 0), (370, 410), (370, 480), (330, 510), (265, 510), (185, 510), (135, 480), (110, 450),
		(110, 500), (40, 500)]
	r = P.embolden([(True, types, pts)], 420, 60, 60, True, [0, 500, 700])
	assert max(y for _, y in r["pts"][0]) <= 510 + 1e-6 and min(y for _, y in r["pts"][0]) >= -1e-6


def test_width_view():
	H = [(True, ["line"] * 4, [(50, 0), (150, 0), (150, 700), (50, 700)]),
		(True, ["line"] * 4, [(420, 0), (520, 0), (520, 700), (420, 700)]),
		(True, ["line"] * 4, [(50, 335), (520, 335), (520, 355), (50, 355)])]
	polys, roles = P.flatten_contours(H)
	assert P.scanline_runs(polys, roles, 200) == [(50, 150, True), (150, 420, False), (420, 520, True)]
	assert P.scanline_runs(polys, roles, 345) == [(50, 520, True)]  # kontur bertumpuk digabung
	assert P.scanline_runs(polys, roles, 285, True) == [(335, 355, True)]
	assert P.scanline_runs(polys, roles, 900) == []
	assert P.parse_heights("x/2, H/2, 300, x*0.25", {"x": 500, "H": 700}) == [250, 350, 300, 125]
	m = P.measure_paths(H)
	chosen = P.label_positions(m["samples"], 34)
	assert chosen and all(abs(a[0] - b[0]) + abs(a[1] - b[1]) > 0 for a, b in zip(chosen, chosen[1:]))
	assert not any(c[2].get("terminal") for c in chosen)
	blocked = P.label_positions(m["samples"], 34, [(c[0], c[1]) for c in chosen])
	assert blocked == []  # semua posisi sudah terisi -> tidak ada label bertumpuk
	# reporter: kotak angka tanpa kursor
	f = make_font()
	l = f.glyphs["H"].layers[f.masters[0].id]
	l.paths = [rect(50, 0, 150, 700), rect(420, 0, 520, 700), rect(50, 335, 520, 355)]
	l.width = 570
	Glyphs.defaults.clear()
	for k in ("showWidthLabels", "showScanH", "showScanV"):
		R.pref_set(k, 1)
	R.pref_set("showIDs", 0)
	R.pref_set("scanHeights", "200")
	R.ReporterPlugin.texts[:] = []
	rep = R.VariableStudioReporter()
	rep.foreground(l)
	labels = [t for t, _ in R.ReporterPlugin.texts]
	for want in ("100", "270", "20", "50"):  # stem, counter, palang, sidebearing kiri/kanan
		assert want in labels, (want, labels)
	R.ReporterPlugin.texts[:] = []
	rep.foreground(l)  # dari cache, hasil sama
	assert sorted(t for t, _ in R.ReporterPlugin.texts) == sorted(labels)
	Glyphs.defaults.clear()


def test_extreme_weights():
	"""Thin -> Black ekstrem: tidak ada goresan/counter terbalik, counter
	tersisa >= 20%, stem tetap tegak, lekuk bahu tetap ada."""
	k = 0.5523

	def ovp(cx, cy, rx, ry, cw=False):
		p = [((cx + rx, cy + ry * k), "offcurve"), ((cx + rx * k, cy + ry), "offcurve"), ((cx, cy + ry), "curve"),
			((cx - rx * k, cy + ry), "offcurve"), ((cx - rx, cy + ry * k), "offcurve"), ((cx - rx, cy), "curve"),
			((cx - rx, cy - ry * k), "offcurve"), ((cx - rx * k, cy - ry), "offcurve"), ((cx, cy - ry), "curve"),
			((cx + rx * k, cy - ry), "offcurve"), ((cx + rx, cy - ry * k), "offcurve"), ((cx + rx, cy), "curve")]
		t = [a[1] for a in p]
		q = [a[0] for a in p]
		if cw:
			q, t = q[::-1], t[::-1]
		return (True, t, q)
	zones = [0, 500, 700]
	o = [ovp(280, 250, 250, 260), ovp(280, 250, 180, 236, True)]
	e = [ovp(260, 250, 230, 260), ovp(260, 330, 160, 110, True)]
	nt = ["line", "line", "line", "offcurve", "offcurve", "curve", "offcurve", "offcurve", "curve", "line", "line",
		"line", "offcurve", "offcurve", "curve", "offcurve", "offcurve", "curve", "line", "line"]
	npts = [(40, 0), (110, 0), (110, 390), (130, 440), (185, 462), (235, 462), (285, 462), (300, 430), (300, 400),
		(300, 0), (370, 0), (370, 410), (370, 480), (330, 510), (265, 510), (185, 510), (135, 480), (110, 450),
		(110, 500), (40, 500)]
	n = [(True, nt, npts)]
	for paths, w in ((o, 560), (e, 520), (n, 420)):
		for ax, ay in ((-60, -30), (150, 100), (250, 160)):
			r = P.embolden(paths, w, ax, ay, True, zones)
			for (c, t, src), new in zip(paths, r["pts"]):
				assert (P.signed_area(new) > 0) == (P.signed_area(src) > 0), (ax, "kontur terbalik")
	# o tipis: hairline 24 - 30 tidak boleh terbalik, tersisa >= tebal min
	r = P.embolden(o, 560, -60, -30, True, zones)
	sm = P.thickness_summary(P.measure_paths([(c, t, p) for (c, t, _), p in zip(o, r["pts"])]))
	assert sm["all"][0] >= 4 - 1e-6, sm
	# n hitam: counter tersisa >= 20% dari 190, stem kiri tegak lurus, lekuk masih ada
	r = P.embolden(n, 420, 150, 100, True, zones)
	q = r["pts"][0]
	polys, roles = P.flatten_contours([(True, nt, q)])
	gaps = [b - a for a, b, on in P.scanline_runs(polys, roles, 250) if not on]
	assert gaps and gaps[0] >= 0.2 * 190 - 1e-6, gaps
	assert abs(q[0][0] - q[19][0]) < 1e-6 and abs(q[1][0] - q[2][0]) < 1e-6  # sisi stem lurus
	assert q[17][1] < q[18][1] - 1, (q[17], q[18])  # lekuk bahu (titik 17) masih di bawah atas stem
	# tanpa perlindungan counter n habis -> perlindungan memang bekerja
	r0 = P.embolden(n, 420, 250, 160, True, zones, protect=None)
	r1 = P.embolden(n, 420, 250, 160, True, zones)
	def gap(pts):
		pl, ro = P.flatten_contours([(True, nt, pts)])
		g = [b - a for a, b, on in P.scanline_runs(pl, ro, 250) if not on]
		return g[0] if g else 0
	# tanpa perlindungan sisi dalam stem kiri (1) melewati sisi dalam stem kanan (9)
	assert r0["pts"][0][1][0] > r0["pts"][0][9][0]
	assert r1["pts"][0][9][0] - r1["pts"][0][1][0] >= 0.2 * 190 - 1e-6 and gap(r1["pts"][0]) >= 37
	assert r1["width"] > r0["width"]  # sisa tebal dipindah ke luar: huruf melebar


def test_terminals():
	"""Ujung runcing (ekor di baseline) tetap runcing & tonjolannya terjaga,
	juga saat stem menerima pindahan dari sisi counter; ujung slab serif
	(sisi sejajar) tetap menebal biasa."""
	T = ["line", "line", "line", "offcurve", "offcurve", "curve", "line", "offcurve", "offcurve", "curve",
		"offcurve", "offcurve", "curve", "offcurve", "offcurve", "curve", "line"]
	Pt = [(298, 440), (298, 390), (296, 185), (296, 110), (296, 0), (395, 0), (512, 0), (512, 13), (510, 18),
		(493, 14), (464, 21), (437, 32), (420, 58), (392, 102), (393, 188), (393, 240), (393, 440)]
	tail = (True, T, Pt)
	assert [(a, b) for _, a, b, _ in P.terminal_caps([tail])] == [(6, 9)]
	left = (True, ["line"] * 4, [(100, 0), (200, 0), (200, 440), (100, 440)])
	for paths, idx in (([tail], 0), ([left, tail], 1)):
		for add in (40, 90, 160):
			r = P.embolden(paths, 650, add, add * 0.6, True, [0, 500, 700])
			q = r["pts"][idx]
			assert abs((q[9][1] - q[6][1]) - 14) < 1e-6, (add, "ujung tumpul")
			assert q[6][0] - q[15][0] >= 119 - 1e-6, (add, "ekor memendek", q[6][0] - q[15][0])
			assert abs(q[6][1]) < 1e-6  # ujung tetap di baseline
	# sisi lurus tetangga tidak miring walau sinar sudutnya menyusur tepi
	r = P.embolden([left, tail], 650, 160, 96, True, [0, 500, 700])
	assert abs(r["pts"][0][1][0] - r["pts"][0][2][0]) < 1e-6
	# tanpa ujung stabil: ujung membengkak (opsi memang berpengaruh)
	r0 = P.embolden([tail], 650, 160, 96, True, [0, 500, 700], terminals=None)
	assert r0["pts"][0][9][1] - r0["pts"][0][6][1] > 50
	# slab serif: ujung 30 tinggi dengan sisi sejajar -> bukan ujung runcing
	slab = (True, ["line"] * 8, [(0, 0), (300, 0), (300, 30), (190, 30), (190, 400), (110, 400), (110, 30), (0, 30)])
	assert P.terminal_caps([slab]) == []


def test_point_audit():
	"""Audit kecocokan titik: urutan kontur, arah, titik awal, titik lebih,
	tipe beda - dengan lokasi; perbaikan otomatis; tunjukkan titik."""
	f = make_font()
	light, bold = f.masters
	g = f.glyphs["o"]
	lb = g.layers[bold.id]
	# Bold: tukar urutan kontur, balik arah kontur luar & geser titik awalnya
	outer, inner = lb.paths
	lb.paths = [inner, outer]
	outer.reverse()
	outer.nodes[5].makeNodeFirst()
	items = P.glyph_issue_items(g, f)
	kinds = sorted(set(it["kind"] for it in items))
	assert kinds == ["direction", "order", "start"], [it["text"] for it in items]
	assert all(it["source"] == "Bold" for it in items)
	start = [it for it in items if it["kind"] == "start"][0]
	assert start["nodes"] and start["text"].endswith("%d:%d" % start["nodes"][0])
	ch = P.fix_glyph(g, f)
	assert any("urutan" in c for c in ch) and any("arah" in c for c in ch), ch
	assert P.check_glyph(g, f) == [], P.check_glyph(g, f)
	ref = g.layers[light.id]
	assert [n.type for n in lb.paths[0].nodes] == [n.type for n in ref.paths[0].nodes]
	# titik lebih: lokasi tepat, tidak diubah otomatis
	h = f.glyphs["H"].layers[bold.id]
	h.paths[0].nodes.insert(2, GSNode((100, 350), "line"))
	h.paths[0].nodes[2].parent = h.paths[0]
	items = P.glyph_issue_items(f.glyphs["H"], f)
	extra = [it for it in items if it["kind"] == "extra"]
	assert len(extra) == 1 and extra[0]["nodes"] == [(0, 2)] and "0:2" in extra[0]["text"], [it["text"] for it in items]
	ch = P.fix_glyph(f.glyphs["H"], f)
	assert any("manual" in c and "0:2" in c for c in ch), ch
	# tipe beda
	f2 = make_font()
	l2 = f2.glyphs["H"].layers[f2.masters[1].id]
	l2.paths[2].nodes[1].type = "curve"
	items = P.glyph_issue_items(f2.glyphs["H"], f2)
	assert [it["kind"] for it in items] == ["type"] and items[0]["nodes"] == [(2, 1)]
	# jendela: Tunjukkan titik memilih titik bermasalah di master Bold
	Glyphs.font = f
	f.selectedLayers = [f.glyphs["H"].layers[light.id]]
	w = P.VariableStudioWindow()
	t = w.w.tabs[0]
	f.selectedLayers = [f.glyphs["H"].layers[bold.id]]
	w.check_current()
	rows = t.results.get()
	t.results.selection = [next(k for k, r in enumerate(rows) if "LEBIH" in r["issue"])]
	w.locate_issue()
	assert h.selection == [h.paths[0].nodes[2]] and "0:2" in t.status.value, t.status.value
	w.w.binds["close"](None)
	# reporter: penanda dengan label
	Glyphs.defaults.clear()
	R.pref_set("showIDs", 0)
	R.ReporterPlugin.texts[:] = []
	rep = R.VariableStudioReporter()
	rep.foreground(h)
	labels = [x for x, _ in R.ReporterPlugin.texts]
	assert any("lebih" in x for x in labels), labels
	R.ReporterPlugin.texts[:] = []
	rep.foreground(f.glyphs["H"].layers[light.id])
	assert not any("lebih" in x for x in R.ReporterPlugin.texts)


def test_direction_bias():
	"""Arah perubahan tebal: kiri/kanan, bawah/atas, dalam/luar; total tetap."""
	stem = [(True, ["line"] * 4, [(100, 0), (180, 0), (180, 600), (100, 600)])]
	bar = [(True, ["line"] * 4, [(0, 300), (400, 300), (400, 340), (0, 340)])]

	def xs(r, k=0):
		return round(min(p[0] for p in r["pts"][k])), round(max(p[0] for p in r["pts"][k]))

	def ys(r, k=0):
		return round(min(p[1] for p in r["pts"][k])), round(max(p[1] for p in r["pts"][k]))
	assert xs(P.embolden(stem, 300, 60, 0, False, None, bias=(-1, 0, 0))) == (40, 180)
	assert xs(P.embolden(stem, 300, 60, 0, False, None)) == (70, 210)
	assert xs(P.embolden(stem, 300, 60, 0, False, None, bias=(1, 0, 0))) == (100, 240)
	assert xs(P.embolden(stem, 300, 60, 0, False, None, bias=(0.5, 0, 0))) == (85, 225)
	assert xs(P.embolden(stem, 300, -40, 0, False, None, bias=(1, 0, 0))) == (100, 140)
	assert xs(P.embolden(stem, 300, -40, 0, False, None, bias=(-1, 0, 0))) == (140, 180)
	assert ys(P.embolden(bar, 400, 0, 30, False, None, bias=(0, 1, 0))) == (300, 370)
	assert ys(P.embolden(bar, 400, 0, 30, False, None, bias=(0, -1, 0))) == (270, 340)
	# dalam: bentuk luar o tetap, counter menyempit; luar: counter tetap
	f = make_font()
	o = contrast_o(f, f.masters[0])
	paths = P.layer_paths(o)
	r_in = P.embolden(paths, 600, 60, 40, False, None, bias=(0, 0, -1))
	r_out = P.embolden(paths, 600, 60, 40, False, None, bias=(0, 0, 1))
	assert xs(r_in, 0) == (20, 580) and xs(r_in, 1) == (200, 400), (xs(r_in, 0), xs(r_in, 1))
	assert xs(r_out, 0) == (-40, 640) and xs(r_out, 1) == (140, 460), (xs(r_out, 0), xs(r_out, 1))
	# jendela: slider arah dipakai tombol terapkan
	f.selectedLayers = [f.glyphs["H"].layers[f.masters[0].id]]
	h = f.glyphs["H"].layers[f.masters[0].id]
	h.paths = [rect(50, 0, 70, 700), rect(430, 0, 450, 700), rect(50, 330, 450, 340)]
	w = P.VariableStudioWindow()
	t = w.w.tabs[6]
	t.keepSB.value = False
	t.addX.value, t.addY.value = "40", "0"
	t.biasX.value = 100
	w.on_bias()
	assert "kanan 100%" in t.biasInfo.value
	t.apply.press()
	assert not GlyphsApp.MESSAGES, GlyphsApp.MESSAGES
	assert (h.paths[0].nodes[0].position.x, h.paths[0].nodes[1].position.x) == (50, 110), [P._xy(n) for n in h.paths[0].nodes]
	t.biasReset.press()
	assert w._weight_bias() is None
	w.w.binds["close"](None)


if __name__ == "__main__":
	n = 0
	for name, fn in sorted(globals().items()):
		if name.startswith("test_") and callable(fn):
			GlyphsApp.MESSAGES[:] = []
			fn()
			n += 1
	print("OK: %d tes lulus" % n)
