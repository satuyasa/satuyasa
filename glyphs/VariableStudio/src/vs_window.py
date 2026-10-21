# encoding: utf-8
# Variable Studio - jendela utama (Window > Variable Studio).
# Tab: Titik, Axis & Master, Style, Italic, Master Baru, Pratinjau.

from __future__ import division, print_function, unicode_literals
import math
import time
import traceback

import objc
from GlyphsApp import Glyphs, Message, UPDATEINTERFACE, WINDOW_MENU
from GlyphsApp.plugins import GeneralPlugin
from AppKit import NSMenuItem, NSPasteboard, NSTimer
from vanilla import (Window, Tabs, TextBox, Button, CheckBox, List, PopUpButton,
	EditText, Slider, TextEditor, HorizontalLine)
from vs_core import *  # BUILD:STRIP
from vs_core import _xy  # BUILD:STRIP
from vs_glyphs import *  # BUILD:STRIP


def _num(text, default=None):
	try:
		return float(str(text).replace(",", ".").strip())
	except Exception:
		if default is None:
			raise ValueError("Bukan angka: %r" % text)
		return default


def _alert(title, text):
	try:
		Message(title=title, message=text, OKButton=None)
	except Exception:
		print(title, text)


class _Batch(object):
	"""Matikan pembaruan antarmuka Glyphs selama banyak perubahan."""

	def __init__(self, font):
		self.font = font

	def __enter__(self):
		try:
			self.font.disableUpdateInterface()
		except Exception:
			pass
		return self

	def __exit__(self, *exc):
		try:
			self.font.enableUpdateInterface()
		except Exception:
			pass
		return False


class VariableStudioWindow(object):

	def __init__(self):
		self.timer = None
		self.phase_start = None
		self.last_sig = None
		self.scan_results = []
		self.point_columns = None
		self.plan = []

		self.w = Window((720, 640), "Variable Studio %s" % VS_VERSION, minSize=(600, 560))
		self.w.tabs = Tabs((10, 10, -10, -10), ["Titik", "Axis & Master", "Style", "Italic", "Master Baru", "Pratinjau", "Tebal / Tipis"])
		self._build_points(self.w.tabs[0])
		self._build_axes(self.w.tabs[1])
		self._build_styles(self.w.tabs[2])
		self._build_italic(self.w.tabs[3])
		self._build_newmaster(self.w.tabs[4])
		self._build_preview(self.w.tabs[5])
		self._build_weight(self.w.tabs[6])
		self.w.bind("close", self.on_close)
		Glyphs.addCallback(self.on_update, UPDATEINTERFACE)
		self.refresh_all()
		self.w.open()

	# ------------------------------------------------------------------ util

	@property
	def font(self):
		return Glyphs.font

	def _layer(self):
		f = self.font
		if f is None:
			return None
		try:
			layers = f.selectedLayers
			return layers[0] if layers else None
		except Exception:
			return None

	def _guard(self, fn, *args):
		try:
			return fn(*args)
		except ValueError as e:
			_alert("Variable Studio", str(e))
		except Exception:
			print(traceback.format_exc())
			_alert("Variable Studio", "Terjadi galat, lihat Window > Macro Panel.")

	def refresh_all(self):
		self.refresh_points()
		self.refresh_axes()
		self.refresh_popups()
		self.refresh_preview_axis()

	def on_update(self, sender=None):
		if self.w.tabs.get() != 0:
			return
		layer = self._layer()
		if layer is None:
			return
		try:
			sig = (id(layer), tuple(_xy(n) for p in layer.paths for n in p.nodes))
		except Exception:
			sig = None
		if sig != self.last_sig:
			self.last_sig = sig
			self.refresh_points()

	def on_close(self, sender):
		self.stop_timer()
		pref_set("previewOn", 0)
		try:
			Glyphs.removeCallback(self.on_update, UPDATEINTERFACE)
		except Exception:
			pass
		Glyphs.redraw()

	# ------------------------------------------------------------ tab Titik

	def _build_points(self, t):
		t.glyph = TextBox((10, 10, -260, 17), "")
		t.selOnly = CheckBox((-250, 8, 140, 20), "Hanya terpilih", callback=lambda s: self.refresh_points())
		t.csv = Button((-105, 6, 95, 22), "Salin CSV", callback=lambda s: self._guard(self.copy_csv))
		t.points = List((10, 36, -10, 210), [])
		t.check = Button((10, 254, 140, 22), "Cek glyph ini", callback=lambda s: self._guard(self.check_current))
		t.fix = Button((156, 254, 230, 22), "Samakan arah & titik awal", callback=lambda s: self._guard(self.fix_current))
		t.line = HorizontalLine((10, 286, -10, 1))
		t.scan = Button((10, 296, 160, 22), "Cek seluruh font", callback=lambda s: self._guard(self.scan))
		t.scanSel = CheckBox((180, 297, 220, 20), "Hanya glyph terpilih")
		t.fixAll = Button((-200, 296, 190, 22), "Perbaiki semua hasil", callback=lambda s: self._guard(self.fix_results))
		t.results = List((10, 326, -10, -66), [],
			columnDescriptions=[{"title": "Glyph", "key": "glyph", "width": 140}, {"title": "Masalah", "key": "issue"}],
			doubleClickCallback=lambda s: self._guard(self.open_results, True))
		t.color = PopUpButton((10, -56, 140, 22), [n for n, _ in GLYPH_COLORS])
		t.paint = Button((156, -57, 150, 22), "Beri warna (flag)", callback=lambda s: self._guard(self.paint_results))
		t.openTab = Button((-170, -57, 160, 22), "Buka hasil di tab", callback=lambda s: self._guard(self.open_results, False))
		t.status = TextBox((10, -26, -10, 17), "", sizeStyle="small")

	def refresh_points(self):
		t = self.w.tabs[0]
		layer = self._layer()
		font = self.font
		if layer is None or font is None:
			t.glyph.set("Pilih satu glyph di Edit View.")
			t.points.set([])
			return
		glyph = layer.parent
		srcs = glyph_sources(glyph, font)
		names = [s[0] for s in srcs]
		cols = [{"title": "ID", "key": "id", "width": 50}, {"title": "Tipe", "key": "type", "width": 62},
			{"title": "Halus", "key": "smooth", "width": 40}]
		for k, n in enumerate(names):
			cols.append({"title": n, "key": "m%d" % k, "width": 110})
		if cols != self.point_columns:
			del t.points
			t.points = List((10, 36, -10, 210), [], columnDescriptions=cols)
			self.point_columns = cols
		all_rows = [read_layer_points(s[2]) for s in srcs]
		cur = read_layer_points(layer, selected_only=bool(t.selOnly.get()))
		wanted = set(r["id"] for r in cur)
		items = []
		base = all_rows[0] if all_rows else []
		for i, r in enumerate(base):
			if r["id"] not in wanted:
				continue
			row = {"id": r["id"], "type": r["type"], "smooth": "ya" if r["smooth"] else ""}
			for k, rows in enumerate(all_rows):
				if i < len(rows):
					mark = "" if rows[i]["type"] == r["type"] else "  !"
					row["m%d" % k] = "%s, %s%s" % (fmt_num(rows[i]["x"]), fmt_num(rows[i]["y"]), mark)
				else:
					row["m%d" % k] = "-"
			items.append(row)
		t.points.set(items)
		issues = check_glyph(glyph, font)
		total = sum(len(p.nodes) for p in layer.paths)
		t.glyph.set("/%s  -  %d kontur, %d titik  -  %s" % (glyph.name, len(layer.paths), total,
			"kompatibel" if not issues else "%d masalah" % len(issues)))

	def copy_csv(self):
		layer = self._layer()
		if layer is None:
			raise ValueError("Pilih glyph dulu.")
		srcs = glyph_sources(layer.parent, self.font)
		text = points_to_csv([read_layer_points(s[2]) for s in srcs], [s[0] for s in srcs])
		pb = NSPasteboard.generalPasteboard()
		pb.clearContents()
		pb.setString_forType_(text, "public.utf8-plain-text")
		self.w.tabs[0].status.set("CSV disalin ke clipboard.")

	def check_current(self):
		layer = self._layer()
		if layer is None:
			raise ValueError("Pilih glyph dulu.")
		issues = check_glyph(layer.parent, self.font)
		self.scan_results = [(layer.parent.name, issues)] if issues else []
		self._show_results()
		self.w.tabs[0].status.set("Kompatibel di semua master." if not issues else "%d masalah." % len(issues))

	def fix_current(self):
		layer = self._layer()
		if layer is None:
			raise ValueError("Pilih glyph dulu.")
		glyph = layer.parent
		glyph.beginUndo()
		try:
			changes = fix_glyph(glyph, self.font)
		finally:
			glyph.endUndo()
		self.refresh_points()
		self.w.tabs[0].status.set("; ".join(changes) if changes else "Tidak ada yang perlu diubah.")

	def _scan_glyphs(self):
		font = self.font
		if self.w.tabs[0].scanSel.get():
			return [l.parent for l in font.selectedLayers]
		return list(font.glyphs)

	def scan(self):
		if self.font is None:
			raise ValueError("Buka font dulu.")
		t0 = time.time()
		self.scan_results = scan_font(self.font, self._scan_glyphs())
		self._show_results()
		self.w.tabs[0].status.set("%d glyph bermasalah (%.1f dtk)." % (len(self.scan_results), time.time() - t0))

	def _show_results(self):
		items = []
		for name, issues in self.scan_results:
			for k, issue in enumerate(issues):
				items.append({"glyph": name if k == 0 else "", "issue": issue, "name": name})
		self.w.tabs[0].results.set(items)

	def _result_names(self, selected_only):
		t = self.w.tabs[0]
		items = t.results.get()
		idx = t.results.getSelection() if selected_only else range(len(items))
		names = []
		for i in idx:
			n = items[i]["name"]
			if n not in names:
				names.append(n)
		return names

	def open_results(self, selected_only):
		names = self._result_names(selected_only) or self._result_names(False)
		if names:
			self.font.newTab("/" + "/".join(names))

	def paint_results(self):
		color = GLYPH_COLORS[self.w.tabs[0].color.get()][1]
		names = self._result_names(False)
		for n in names:
			g = self.font.glyphs[n]
			if g is not None:
				set_glyph_color(g, color)
		self.w.tabs[0].status.set("%d glyph diberi warna." % len(names))

	def fix_results(self):
		names = self._result_names(False)
		done = 0
		with _Batch(self.font):
			for n in names:
				g = self.font.glyphs[n]
				if g is not None and fix_glyph(g, self.font):
					done += 1
		self.scan()
		self.w.tabs[0].status.set("%d glyph diubah; %d masih bermasalah." % (done, len(self.scan_results)))

	# ----------------------------------------------------- tab Axis & Master

	def _build_axes(self, t):
		t.axesLabel = TextBox((10, 10, -10, 17), "Axis font (min/default/max = koordinat desain master)")
		t.axes = List((10, 30, -10, 110), [], columnDescriptions=[
			{"title": "Nama", "key": "name"}, {"title": "Tag", "key": "tag", "width": 50},
			{"title": "Min", "key": "min", "width": 60}, {"title": "Default", "key": "default", "width": 60},
			{"title": "Max", "key": "max", "width": 60}, {"title": "Tersembunyi", "key": "hidden", "width": 80}])
		t.preset = PopUpButton((10, 150, 170, 22), [p[0] for p in AXIS_PRESETS], callback=self.on_preset)
		t.name = EditText((186, 150, 120, 22), "Weight", placeholder="Nama")
		t.tag = EditText((312, 150, 60, 22), "wght", placeholder="Tag")
		t.value = EditText((378, 150, 60, 22), "400", placeholder="Nilai")
		t.hidden = CheckBox((444, 151, 90, 20), "Sembunyi")
		t.add = Button((-110, 150, 100, 22), "Tambah axis", callback=lambda s: self._guard(self.add_axis))
		t.hint = TextBox((10, 176, -10, 17), "Nilai = koordinat untuk semua master & instance yang sudah ada.", sizeStyle="small")
		t.line = HorizontalLine((10, 200, -10, 1))
		t.mastersLabel = TextBox((10, 210, -10, 17), "Master")
		t.masters = List((10, 230, -10, 150), [], columnDescriptions=[
			{"title": "Master", "key": "name", "width": 150}, {"title": "Lokasi desain", "key": "design"},
			{"title": "Axis Location (user)", "key": "user"}, {"title": "", "key": "origin", "width": 50}],
			allowsMultipleSelection=False)
		t.axisPop = PopUpButton((10, 390, 150, 22), [])
		t.axisVal = EditText((166, 390, 70, 22), "")
		t.setVal = Button((242, 390, 170, 22), "Set nilai master", callback=lambda s: self._guard(self.set_master_value))
		t.setUser = Button((418, 390, -10, 22), "Set Axis Location (user)", callback=lambda s: self._guard(self.set_master_user))
		t.origin = Button((10, 420, 200, 22), "Jadikan origin (default)", callback=lambda s: self._guard(self.set_origin))
		t.varSetting = Button((216, 420, -10, 22), "Tambah Variable Font Setting", callback=lambda s: self._guard(self.add_var_setting))
		t.line2 = HorizontalLine((10, 452, -10, 1))
		t.vmLabel = TextBox((10, 462, -10, 17), "Virtual Master (mis. untuk axis yang tidak punya master sendiri), satu axis per baris: Nama=nilai")
		t.vm = TextEditor((10, 482, -170, 60), "Weight=1000")
		t.vmAdd = Button((-160, 482, 150, 22), "Tambah Virtual Master", callback=lambda s: self._guard(self.add_virtual_master))
		t.status = TextBox((10, -26, -10, 17), "", sizeStyle="small")

	def on_preset(self, sender):
		t = self.w.tabs[1]
		label, name, tag, value = AXIS_PRESETS[sender.get()]
		t.name.set(name)
		t.tag.set(tag)
		t.value.set(fmt_num(value))

	def refresh_axes(self):
		t = self.w.tabs[1]
		font = self.font
		if font is None:
			t.axes.set([])
			t.masters.set([])
			return
		triples = axis_triples(font) if len(font.masters) else []
		items = []
		for info, tr in zip(axes_info(font), triples):
			items.append({"name": info["name"], "tag": info["tag"], "min": fmt_num(tr[0]),
				"default": fmt_num(tr[1]), "max": fmt_num(tr[2]), "hidden": "ya" if info["hidden"] else ""})
		t.axes.set(items)
		origin = origin_master(font) if len(font.masters) else None
		ms = []
		for m in font.masters:
			loc = axis_location_dict(m)
			ms.append({"name": m.name, "design": describe_location(font, axis_values(m, font)),
				"user": ", ".join("%s %s" % (k, fmt_num(v)) for k, v in loc.items()) or "-",
				"origin": "origin" if m is origin else ""})
		t.masters.set(ms)
		t.axisPop.setItems([a.name for a in font.axes])

	def refresh_popups(self):
		font = self.font
		masters = [m.name for m in font.masters] if font else []
		axes = [a.name for a in font.axes] if font else []
		n = self.w.tabs[4]
		n.a.setItems(masters)
		n.b.setItems(masters)
		if len(masters) > 1:
			n.b.set(len(masters) - 1)
		n.axis.setItems(axes)
		n.inst.setItems([i.name for i in font.instances if is_static(i)] if font else [])
		self.w.tabs[3].masters.set([{"name": m} for m in masters])
		wt = self.w.tabs[6]
		wt.src.setItems(masters)
		cur = wt.target.get()
		wt.target.setItems(["Master baru"] + masters)
		wt.target.set(cur if cur < len(masters) + 1 else 0)
		wt.axis.setItems(axes)
		self.on_weight_target()

	def _changed(self):
		self.refresh_all()
		Glyphs.redraw()

	def add_axis(self):
		t = self.w.tabs[1]
		font = self.font
		if font is None:
			raise ValueError("Buka font dulu.")
		with _Batch(font):
			add_axis(font, t.name.get(), t.tag.get(), _num(t.value.get()), bool(t.hidden.get()))
		t.status.set("Axis %s ditambahkan." % t.name.get())
		self._changed()

	def _selected_master(self):
		t = self.w.tabs[1]
		sel = t.masters.getSelection()
		if not sel:
			raise ValueError("Pilih master di daftar.")
		return self.font.masters[sel[0]]

	def set_master_value(self):
		t = self.w.tabs[1]
		m = self._selected_master()
		set_axis_value(m, self.font, t.axisPop.get(), _num(t.axisVal.get()))
		self._changed()

	def set_master_user(self):
		t = self.w.tabs[1]
		font = self.font
		m = self._selected_master()
		loc = axis_location_dict(m)
		if not loc:
			loc = dict((a.name, v) for a, v in zip(font.axes, axis_values(m, font)))
		loc[font.axes[t.axisPop.get()].name] = _num(t.axisVal.get())
		set_axis_location(m, font, loc)
		t.status.set("Ingat: Axis Location harus ada di semua master (dan instance).")
		self._changed()

	def set_origin(self):
		set_origin(self.font, self._selected_master())
		self._changed()

	def add_var_setting(self):
		inst = add_variable_setting(self.font)
		self.w.tabs[1].status.set("Variable Font Setting ditambahkan." if inst else "Sudah ada.")

	def add_virtual_master(self):
		t = self.w.tabs[1]
		vals = dict(parse_style_list(t.vm.get()))
		names = [a.name for a in self.font.axes]
		for k in vals:
			if k not in names:
				raise ValueError("Axis %s tidak ada di font." % k)
		add_virtual_master(self.font, vals)
		t.status.set("Virtual Master ditambahkan ke Font Info > Custom Parameters.")

	# ------------------------------------------------------------ tab Style

	def _build_styles(self, t):
		t.wLabel = TextBox((10, 10, 200, 17), "Tebal (Nama=nilai user)")
		t.weights = TextEditor((10, 30, 200, 190), style_list_text(WEIGHT_PRESETS))
		t.dLabel = TextBox((220, 10, 200, 17), "Lebar (Nama=nilai user %)")
		t.widths = TextEditor((220, 30, 200, 190), "Normal=100")
		t.p1 = Button((430, 30, -10, 22), "Thin - Black", callback=lambda s: t.weights.set(style_list_text(WEIGHT_PRESETS)))
		t.p2 = Button((430, 58, -10, 22), "Light - Bold", callback=lambda s: t.weights.set(
			style_list_text([p for p in WEIGHT_PRESETS if 300 <= p[1] <= 700])))
		t.p3 = Button((430, 86, -10, 22), "Hanya Normal", callback=lambda s: t.widths.set("Normal=100"))
		t.p4 = Button((430, 114, -10, 22), "Condensed - Expanded", callback=lambda s: t.widths.set(
			style_list_text([p for p in WIDTH_PRESETS if 75 <= p[1] <= 125])))
		t.p5 = Button((430, 142, -10, 22), "Semua lebar", callback=lambda s: t.widths.set(style_list_text(WIDTH_PRESETS)))
		t.upright = CheckBox((10, 228, 90, 20), "Tegak", value=True)
		t.italic = CheckBox((100, 228, 90, 20), "Italic", value=True)
		t.mapLabel = TextBox((200, 230, 70, 17), "Pemetaan")
		t.mapping = PopUpButton((270, 228, -10, 22), [m[1] for m in MAPPING_MODES])
		t.axisLoc = CheckBox((10, 254, 200, 20), "Tulis Axis Location", value=True)
		t.axisMap = CheckBox((210, 254, 220, 20), "Tulis Axis Mappings (avar)")
		t.link = CheckBox((430, 254, -10, 20), "Style linking", value=True)
		t.elide = CheckBox((10, 276, 200, 20), "Elidable STAT (Regular)", value=True)
		t.replace = CheckBox((210, 276, 220, 20), "Ganti instance bernama sama", value=True)
		t.varSet = CheckBox((430, 276, -10, 20), "Variable setting", value=True)
		t.preview = Button((10, 304, 160, 22), "Pratinjau rencana", callback=lambda s: self._guard(self.preview_plan))
		t.make = Button((-200, 304, 190, 22), "Buat instance", callback=lambda s: self._guard(self.make_instances))
		t.plan = List((10, 334, -10, -34), [], columnDescriptions=[
			{"title": "Instance", "key": "name", "width": 170}, {"title": "Desain", "key": "design"},
			{"title": "User", "key": "user"}, {"title": "Link", "key": "link", "width": 110}])
		t.status = TextBox((10, -26, -10, 17), "", sizeStyle="small")

	def _plan(self):
		t = self.w.tabs[2]
		font = self.font
		if font is None or not len(font.masters):
			raise ValueError("Buka font dulu.")
		weights = parse_style_list(t.weights.get())
		widths = parse_style_list(t.widths.get()) or [("Normal", 100.0)]
		if not weights:
			raise ValueError("Daftar tebal kosong.")
		if not t.upright.get() and not t.italic.get():
			raise ValueError("Pilih Tegak dan/atau Italic.")
		plan = build_instance_plan(weights, widths, bool(t.upright.get()), bool(t.italic.get()))
		mode = MAPPING_MODES[t.mapping.get()][0]
		return plan_with_design(font, plan, mode)

	def preview_plan(self):
		planned, warnings = self._plan()
		font = self.font
		items = []
		for p in planned:
			flags = []
			if p["isBold"]:
				flags.append("B")
			if p["isItalic"]:
				flags.append("I")
			items.append({"name": p["name"], "design": describe_location(font, p["design"]),
				"user": ", ".join("%s %s" % (k, fmt_num(v)) for k, v in p["user"].items()),
				"link": ("%s %s" % (p["linkStyle"], "".join(flags))).strip()})
		self.w.tabs[2].plan.set(items)
		self.w.tabs[2].status.set(("%d instance. " % len(planned)) + " ".join(warnings))
		return planned

	def make_instances(self):
		t = self.w.tabs[2]
		planned = self.preview_plan()
		font = self.font
		with _Batch(font):
			created, skipped = generate_instances(font, planned, replace=bool(t.replace.get()),
				axis_location=bool(t.axisLoc.get()), axis_mappings=bool(t.axisMap.get()),
				style_linking=bool(t.link.get()), elidable=bool(t.elide.get()),
				variable_setting=bool(t.varSet.get()))
		msg = "%d instance dibuat." % len(created)
		if skipped:
			msg += " Dilewati (sudah ada): %s." % ", ".join(skipped)
		t.status.set(msg)
		self._changed()

	# ----------------------------------------------------------- tab Italic

	def _build_italic(self, t):
		t.aLabel = TextBox((10, 12, 100, 17), "Sudut (°)")
		t.angle = EditText((110, 10, 60, 22), "10")
		t.oLabel = TextBox((190, 12, 60, 17), "Pusat")
		t.origin = PopUpButton((250, 10, 180, 22), ["Setengah x-height", "Garis dasar (baseline)"])
		t.comp = CheckBox((10, 40, -10, 20), "Kompensasi tebal stem (lebarkan 1/cos sudut)")
		t.axLabel = TextBox((10, 68, 100, 17), "Axis")
		t.axis = PopUpButton((110, 66, 320, 22), ["ital (tegak 0, miring 1)", "slnt (0 ke -sudut)", "Tanpa axis (file terpisah)"])
		t.sLabel = TextBox((10, 98, 100, 17), "Akhiran nama")
		t.suffix = EditText((110, 96, 120, 22), "Italic")
		t.kern = CheckBox((250, 97, -10, 20), "Salin kerning", value=True)
		t.mLabel = TextBox((10, 128, -10, 17), "Master sumber (kosong = semua master yang tidak miring):")
		t.masters = List((10, 148, -10, 160), [], columnDescriptions=[{"title": "Master", "key": "name"}])
		t.make = Button((10, 318, 220, 22), "Buat master italic", callback=lambda s: self._guard(self.make_italic))
		t.note = TextBox((10, 350, -10, 90),
			"Path & anchor dimiringkan (skew) di sekitar pusat yang dipilih; komponen hanya digeser karena "
			"glyph dasarnya ikut dimiringkan. Hasilnya titik awal yang sama dengan master tegak, jadi tetap "
			"kompatibel. Setelah itu rapikan bentuk (o, s, e, lengkung) secara manual, lalu buat instance "
			"dengan centang Italic di tab Style.", sizeStyle="small")
		t.status = TextBox((10, -26, -10, 17), "", sizeStyle="small")

	def make_italic(self):
		t = self.w.tabs[3]
		font = self.font
		if font is None:
			raise ValueError("Buka font dulu.")
		sel = t.masters.getSelection()
		if sel:
			masters = [font.masters[i] for i in sel]
		else:
			masters = [m for m in font.masters if not (m.italicAngle or 0)]
		if not masters:
			raise ValueError("Tidak ada master tegak.")
		angle = _num(t.angle.get())
		mode = ["ital", "slnt", "none"][t.axis.get()]
		with _Batch(font):
			new, odd = make_italic_masters(font, masters, angle, "xheight" if t.origin.get() == 0 else "baseline",
				bool(t.comp.get()), mode, t.suffix.get().strip() or "Italic", bool(t.kern.get()))
		msg = "%d master italic dibuat." % len(new)
		if odd:
			msg += " Cek komponen bertransformasi: %s" % ", ".join(odd[:12])
		t.status.set(msg)
		self._changed()

	# ------------------------------------------------------ tab Master Baru

	def _build_newmaster(self, t):
		t.info = TextBox((10, 10, -10, 34),
			"Buat master baru dari dua master. Nilai di luar rentang A-B = ekstrapolasi, mis. Thin atau Black "
			"dari Regular + Bold. Glyph yang tidak kompatibel disalin dari A dan dilaporkan.", sizeStyle="small")
		t.aLabel = TextBox((10, 54, 80, 17), "Master A")
		t.a = PopUpButton((90, 52, 200, 22), [])
		t.bLabel = TextBox((310, 54, 80, 17), "Master B")
		t.b = PopUpButton((390, 52, -10, 22), [])
		t.axLabel = TextBox((10, 86, 80, 17), "Axis")
		t.axis = PopUpButton((90, 84, 200, 22), [])
		t.vLabel = TextBox((310, 86, 80, 17), "Nilai desain")
		t.value = EditText((390, 84, 80, 22), "100")
		t.nLabel = TextBox((10, 118, 80, 17), "Nama")
		t.name = EditText((90, 116, 200, 22), "Thin")
		t.kern = CheckBox((310, 117, -10, 20), "Interpolasi kerning", value=True)
		t.make = Button((10, 150, 200, 22), "Buat master", callback=lambda s: self._guard(self.make_between))
		t.line = HorizontalLine((10, 186, -10, 1))
		t.iLabel = TextBox((10, 198, -10, 17), "Instance -> master (pakai interpolasi Glyphs sendiri):")
		t.inst = PopUpButton((10, 220, 280, 22), [])
		t.toMaster = Button((300, 220, 200, 22), "Jadikan master", callback=lambda s: self._guard(self.inst_to_master))
		t.bad = List((10, 256, -10, -34), [], columnDescriptions=[{"title": "Perlu dicek", "key": "name"}])
		t.status = TextBox((10, -26, -10, 17), "", sizeStyle="small")

	def make_between(self):
		t = self.w.tabs[4]
		font = self.font
		ma, mb = font.masters[t.a.get()], font.masters[t.b.get()]
		if ma is mb:
			raise ValueError("Pilih dua master berbeda.")
		name = t.name.get().strip()
		if not name:
			raise ValueError("Isi nama master.")
		with _Batch(font):
			nm, bad = make_master_between(font, ma, mb, t.axis.get(), _num(t.value.get()), name, bool(t.kern.get()))
		t.bad.set([{"name": n} for n in bad])
		t.status.set("Master %s dibuat. %d glyph tidak kompatibel (disalin dari A)." % (nm.name, len(bad)))
		self._changed()

	def inst_to_master(self):
		t = self.w.tabs[4]
		font = self.font
		statics = [i for i in font.instances if is_static(i)]
		if not statics:
			raise ValueError("Belum ada instance.")
		with _Batch(font):
			nm, missing = instance_to_master(font, statics[t.inst.get()])
		t.bad.set([{"name": n} for n in missing])
		t.status.set("Master %s dibuat dari instance." % nm.name)
		self._changed()

	# --------------------------------------------------------- tab Pratinjau

	def _build_preview(self, t):
		t.on = CheckBox((10, 10, -10, 20), "Pratinjau interpolasi (isi abu-abu + titik oranye)", value=pref_bool("previewOn", False),
			callback=self.on_pref)
		t.ids = CheckBox((10, 34, 200, 20), "ID titik (kontur:indeks)", value=pref_bool("showIDs", True), callback=self.on_pref)
		t.idsOff = CheckBox((220, 34, -10, 20), "termasuk off-curve", value=pref_bool("idsOffcurve", False), callback=self.on_pref)
		t.ghosts = CheckBox((10, 58, 200, 20), "Bayangan semua master", value=pref_bool("showGhosts", True), callback=self.on_pref)
		t.trails = CheckBox((220, 58, -10, 20), "Jalur titik antar master", value=pref_bool("showTrails", True), callback=self.on_pref)
		t.brace = CheckBox((10, 82, 200, 20), "Ikutkan brace layer {…}", value=pref_bool("showBrace", True), callback=self.on_pref)
		t.thick = CheckBox((220, 82, -10, 20), "Ukuran tebal garis (biru tipis, merah tebal)",
			value=pref_bool("showThickness", False), callback=self.on_pref)
		t.line = HorizontalLine((10, 110, -10, 1))
		t.axLabel = TextBox((10, 122, 60, 17), "Axis")
		t.axis = PopUpButton((70, 120, 200, 22), [], callback=lambda s: self.refresh_preview_axis())
		t.value = TextBox((290, 122, -10, 17), "")
		t.slider = Slider((10, 150, -10, 23), minValue=0, maxValue=1, value=0, callback=self.on_slider, continuous=True)
		t.fLabel = TextBox((10, 186, 40, 17), "Dari")
		t.vFrom = EditText((50, 184, 80, 22), "")
		t.tLabel = TextBox((140, 186, 30, 17), "ke")
		t.vTo = EditText((170, 184, 80, 22), "")
		t.cLabel = TextBox((270, 186, 60, 17), "Siklus (s)")
		t.cycle = EditText((330, 184, 60, 22), "8")
		t.play = Button((-130, 183, 120, 22), "Putar", callback=self.toggle_play)
		t.minus = Button((10, 216, 40, 22), "[", callback=lambda s: self.step(-1))
		t.plus = Button((56, 216, 40, 22), "]", callback=lambda s: self.step(1))
		t.line2 = HorizontalLine((10, 248, -10, 1))
		t.wTitle = TextBox((10, 256, -10, 17), "Ukuran lebar tanpa kursor (selalu tampil di Edit View):")
		t.wLabels = CheckBox((10, 278, -10, 20), "Label lebar di setiap goresan",
			value=pref_bool("showWidthLabels", False), callback=self.on_pref)
		t.scanH = CheckBox((10, 302, 200, 20), "Garis ukur mendatar di", value=pref_bool("showScanH", False),
			callback=self.on_pref)
		t.heights = EditText((210, 300, 180, 22), pref_get("scanHeights", "x/2, H/2"), callback=self.on_pref)
		t.hHint = TextBox((400, 303, -10, 17), "x, H, a, d, angka; boleh /2", sizeStyle="small")
		t.scanV = CheckBox((10, 326, 250, 20), "Garis ukur tegak di tengah glyph", value=pref_bool("showScanV", False),
			callback=self.on_pref)
		t.gaps = CheckBox((270, 326, -10, 20), "Jarak putih (counter, sidebearing)", value=pref_bool("showGaps", True),
			callback=self.on_pref)
		t.note = TextBox((10, 356, -10, 80),
			"Aktifkan reporter lewat View > Show Variable Studio. Pratinjau memakai model interpolasi "
			"OpenType (sama seperti fontTools) dari semua master dan brace layer; glyph yang tidak kompatibel "
			"tidak digambar. Sumbu lain memakai nilai master origin.", sizeStyle="small")

	def on_pref(self, sender=None):
		t = self.w.tabs[5]
		pref_set("previewOn", int(bool(t.on.get())))
		pref_set("showIDs", int(bool(t.ids.get())))
		pref_set("idsOffcurve", int(bool(t.idsOff.get())))
		pref_set("showGhosts", int(bool(t.ghosts.get())))
		pref_set("showTrails", int(bool(t.trails.get())))
		pref_set("showBrace", int(bool(t.brace.get())))
		pref_set("showThickness", int(bool(t.thick.get())))
		pref_set("showWidthLabels", int(bool(t.wLabels.get())))
		pref_set("showScanH", int(bool(t.scanH.get())))
		pref_set("showScanV", int(bool(t.scanV.get())))
		pref_set("showGaps", int(bool(t.gaps.get())))
		pref_set("scanHeights", t.heights.get() or "")
		Glyphs.redraw()

	def refresh_preview_axis(self):
		t = self.w.tabs[5]
		font = self.font
		if font is None or not len(font.axes):
			t.axis.setItems([])
			return
		names = [a.name for a in font.axes]
		cur = t.axis.get() if t.axis.getItems() == names else 0
		t.axis.setItems(names)
		t.axis.set(max(0, cur))
		lo, df, hi = axis_triples(font)[t.axis.get()]
		t.vFrom.set(fmt_num(lo))
		t.vTo.set(fmt_num(hi))
		loc = preview_location()
		v = loc[t.axis.get()] if len(loc) == len(font.axes) else df
		t.slider.setMinValue(lo)
		t.slider.setMaxValue(hi if hi > lo else lo + 1)
		t.slider.set(v)
		self.set_location_value(v)

	def set_location_value(self, v):
		font = self.font
		if font is None or not len(font.axes):
			return
		t = self.w.tabs[5]
		loc = preview_location()
		if len(loc) != len(font.axes):
			loc = axis_values(origin_master(font), font)
		loc[t.axis.get()] = float(v)
		set_preview_location(loc)
		t.value.set("%s %.2f" % (font.axes[t.axis.get()].axisTag, v))
		Glyphs.redraw()

	def on_slider(self, sender):
		self.set_location_value(sender.get())

	def step(self, direction):
		t = self.w.tabs[5]
		lo, hi = _num(t.vFrom.get()), _num(t.vTo.get())
		v = t.slider.get() + direction * (hi - lo) / 20.0
		v = max(min(v, max(lo, hi)), min(lo, hi))
		t.slider.set(v)
		self.set_location_value(v)

	def toggle_play(self, sender):
		if self.timer is not None:
			self.stop_timer()
			return
		t = self.w.tabs[5]
		if not t.on.get():
			t.on.set(True)
			self.on_pref()
		self.phase_start = time.time()
		self.timer = NSTimer.scheduledTimerWithTimeInterval_repeats_block_(1.0 / 30, True, self.tick)
		t.play.setTitle("Jeda")

	def stop_timer(self):
		if self.timer is not None:
			self.timer.invalidate()
			self.timer = None
		try:
			self.w.tabs[5].play.setTitle("Putar")
		except Exception:
			pass

	def tick(self, timer=None):
		try:
			t = self.w.tabs[5]
			lo, hi = _num(t.vFrom.get(), 0), _num(t.vTo.get(), 1)
			cycle = max(0.5, _num(t.cycle.get(), 8))
			ph = ((time.time() - self.phase_start) % cycle) / cycle
			k = 1 - abs(2 * ph - 1)  # bolak-balik lo -> hi -> lo
			v = lo + (hi - lo) * (0.5 - 0.5 * math.cos(math.pi * k))
			t.slider.set(v)
			self.set_location_value(v)
		except Exception:
			self.stop_timer()
			print(traceback.format_exc())


	# ------------------------------------------------------- tab Tebal / Tipis

	def _build_weight(self, t):
		t.info = TextBox((10, 8, -10, 30),
			"Menebalkan / menipiskan tanpa mengubah jumlah, urutan, atau arah titik (tetap kompatibel). "
			"Nilai = tambahan tebal garis; negatif = lebih tipis.", sizeStyle="small")
		t.xLabel = TextBox((10, 44, 170, 17), "Stem tegak (horizontal)")
		t.addX = EditText((180, 42, 70, 22), "40", callback=lambda s: self.on_weight_target())
		t.yLabel = TextBox((270, 44, 150, 17), "Stem datar (vertikal)")
		t.addY = EditText((420, 42, 70, 22), "30")
		t.keepSB = CheckBox((10, 70, 230, 20), "Pertahankan sidebearing", value=True)
		t.keepH = CheckBox((240, 70, -10, 20), "Pertahankan baseline, x-height, cap height", value=True)
		t.modeLabel = TextBox((10, 100, 60, 17), "Mode")
		t.mode = PopUpButton((70, 98, -10, 22), [m[1] for m in CONTRAST_MODES], callback=lambda s: self.on_weight_mode())
		t.loLabel = TextBox((10, 130, 110, 17), "Garis tipis ≤")
		t.lo = EditText((120, 128, 60, 22), "", placeholder="auto")
		t.hiLabel = TextBox((190, 130, 110, 17), "Garis tebal ≥")
		t.hi = EditText((300, 128, 60, 22), "", placeholder="auto")
		t.scan = Button((-200, 127, 190, 22), "Scan ketebalan", callback=lambda s: self._guard(self.scan_weight))
		t.summary = TextBox((10, 156, -10, 34), "Scan dulu untuk mencari garis tertipis dan tertebal di master sumber.",
			sizeStyle="small")
		t.line = HorizontalLine((10, 194, -10, 1))
		t.lLabel = TextBox((10, 202, 300, 17), "Glyph yang sedang dibuka (bisa di-Undo)")
		t.selOnly = CheckBox((310, 200, -10, 20), "Hanya titik terpilih")
		t.m10 = Button((10, 224, 60, 22), "-10", callback=lambda s: self._guard(self.nudge_layer, -10))
		t.m2 = Button((76, 224, 50, 22), "-2", callback=lambda s: self._guard(self.nudge_layer, -2))
		t.p2 = Button((132, 224, 50, 22), "+2", callback=lambda s: self._guard(self.nudge_layer, 2))
		t.p10 = Button((188, 224, 60, 22), "+10", callback=lambda s: self._guard(self.nudge_layer, 10))
		t.apply = Button((260, 224, -10, 22), "Terapkan nilai di atas ke glyph ini", callback=lambda s: self._guard(self.apply_layer))
		t.line2 = HorizontalLine((10, 256, -10, 1))
		t.sLabel = TextBox((10, 266, 80, 17), "Sumber")
		t.src = PopUpButton((90, 264, 200, 22), [], callback=lambda s: self.on_weight_target())
		t.tLabel = TextBox((310, 266, 60, 17), "Hasil ke")
		t.target = PopUpButton((370, 264, -10, 22), ["Master baru"], callback=lambda s: self.on_weight_target())
		t.nLabel = TextBox((10, 296, 80, 17), "Nama")
		t.name = EditText((90, 294, 200, 22), "Bold")
		t.aLabel = TextBox((310, 296, 60, 17), "Axis")
		t.axis = PopUpButton((370, 294, 150, 22), [], callback=lambda s: self.on_weight_target())
		t.value = EditText((530, 294, -10, 22), "", placeholder="nilai")
		t.glyphSel = CheckBox((10, 322, 330, 20), "Hanya glyph terpilih (scan & master yang ada)")
		t.kern = CheckBox((340, 322, -10, 20), "Salin kerning ke master baru", value=True)
		t.make = Button((10, 348, 240, 22), "Buat / isi master", callback=lambda s: self._guard(self.weight_master))
		t.results = List((10, 380, -10, -34), [], columnDescriptions=[
			{"title": "Glyph", "key": "name", "width": 130}, {"title": "Tertipis", "key": "thin", "width": 70},
			{"title": "Tertebal", "key": "thick", "width": 70}, {"title": "Kontras", "key": "ratio", "width": 70},
			{"title": "Tegak (min-max)", "key": "v"}, {"title": "Datar (min-max)", "key": "h"}],
			doubleClickCallback=lambda s: self._guard(self.open_weight_result))
		t.status = TextBox((10, -26, -10, 17), "", sizeStyle="small")
		t.lo.enable(False)
		t.hi.enable(False)

	def _weight_mode(self):
		return CONTRAST_MODES[self.w.tabs[6].mode.get()][0]

	def on_weight_mode(self):
		t = self.w.tabs[6]
		on = self._weight_mode() != "uniform"
		for k in ("lo", "hi"):
			getattr(t, k).enable(on)
		if on and not (t.lo.get() and t.hi.get()):
			self._guard(self.scan_weight)

	def _weight_limits(self):
		if self._weight_mode() == "uniform":
			return None, None
		t = self.w.tabs[6]
		if not (t.lo.get() and t.hi.get()):
			self.scan_weight()
		lo, hi = _num(t.lo.get()), _num(t.hi.get())
		if hi <= lo:
			raise ValueError("Batas tebal harus lebih besar dari batas tipis.")
		return lo, hi

	def scan_weight(self):
		t = self.w.tabs[6]
		font = self.font
		if font is None or not len(font.masters):
			raise ValueError("Buka font dulu.")
		src = font.masters[t.src.get()]
		glyphs = [l.parent for l in font.selectedLayers] if t.glyphSel.get() else None
		t0 = time.time()
		rows, sm = scan_thickness(font, src, glyphs)
		rows.sort(key=lambda r: r["thin"])
		items = []
		for r in rows:
			items.append({"name": r["name"], "thin": fmt_num(round(r["thin"])), "thick": fmt_num(round(r["thick"])),
				"ratio": "%.1f×" % (r["thick"] / r["thin"]) if r["thin"] else "-",
				"v": "%s-%s" % tuple(fmt_num(round(x)) if x is not None else "-" for x in r["v"]),
				"h": "%s-%s" % tuple(fmt_num(round(x)) if x is not None else "-" for x in r["h"])})
		t.results.set(items)
		if not rows:
			t.summary.set("Tidak ada kontur yang bisa diukur.")
			return
		if sm["lo"] is not None:
			t.lo.set(fmt_num(sm["lo"]))
			t.hi.set(fmt_num(sm["hi"]))
		a, b = sm["thinnest"], sm["thickest"]
		t.summary.set("Master %s: garis tertipis %s (/%s), tertebal %s (/%s). Batas otomatis: tipis ≤ %s, tebal ≥ %s. "
			"Garis di antaranya berubah bertahap." % (src.name, fmt_num(round(a["thin"])), a["name"],
			fmt_num(round(b["thick"])), b["name"], fmt_num(sm["lo"]), fmt_num(sm["hi"])))
		t.status.set("%d glyph diukur (%.1f dtk). Klik dua kali untuk membuka glyph." % (len(rows), time.time() - t0))

	def open_weight_result(self):
		t = self.w.tabs[6]
		items = t.results.get()
		names = [items[i]["name"] for i in t.results.getSelection()] or [i["name"] for i in items[:1]]
		if names:
			self.font.newTab("/" + "/".join(names))

	def _weight_amounts(self):
		t = self.w.tabs[6]
		return _num(t.addX.get()), _num(t.addY.get())

	def on_weight_target(self):
		t = self.w.tabs[6]
		font = self.font
		new = t.target.get() == 0
		for k in ("name", "axis", "value", "kern"):
			getattr(t, k).enable(new)
		if font is None or not len(font.masters) or not new or not len(font.axes):
			return
		try:
			src = font.masters[t.src.get()]
			ax = t.axis.get()
			add_x = _num(t.addX.get(), 0)
			t.value.set(fmt_num(axis_values(src, font)[ax] + add_x))
		except Exception:
			pass

	def _layer_zones(self, layer):
		if not self.w.tabs[6].keepH.get():
			return None
		mid = getattr(layer, "associatedMasterId", None)
		for m in self.font.masters:
			if m.id == mid:
				return master_zones(m)
		return None

	def _embolden_current(self, add_x, add_y):
		t = self.w.tabs[6]
		layer = self._layer()
		if layer is None:
			raise ValueError("Buka glyph di Edit View dulu.")
		glyph = layer.parent
		glyph.beginUndo()
		try:
			lo, hi = self._weight_limits()
			moved = embolden_layer(layer, add_x, add_y, bool(t.keepSB.get()), self._layer_zones(layer),
				bool(t.selOnly.get()), self._weight_mode(), lo, hi)
		finally:
			glyph.endUndo()
		if not moved and t.selOnly.get():
			raise ValueError("Pilih dulu titik on-curve yang mau digeser.")
		t.status.set("/%s: %d titik digeser (%+g, %+g)." % (glyph.name, moved, add_x, add_y))
		self.refresh_points()
		Glyphs.redraw()

	def nudge_layer(self, step):
		add_x, add_y = self._weight_amounts()
		# langkah kecil dengan perbandingan tegak : datar yang sama seperti isian
		ratio = (add_y / add_x) if add_x else 1.0
		self._embolden_current(float(step), float(step) * ratio)

	def apply_layer(self):
		self._embolden_current(*self._weight_amounts())

	def weight_master(self):
		t = self.w.tabs[6]
		font = self.font
		if font is None or not len(font.masters):
			raise ValueError("Buka font dulu.")
		add_x, add_y = self._weight_amounts()
		src = font.masters[t.src.get()]
		target = None if t.target.get() == 0 else font.masters[t.target.get() - 1]
		glyphs = None
		if target is not None and t.glyphSel.get():
			glyphs = [l.parent for l in font.selectedLayers]
		name = t.name.get().strip()
		if target is None and not name:
			raise ValueError("Isi nama master baru.")
		axis_idx = t.axis.get() if len(font.axes) else None
		axis_value = _num(t.value.get()) if (target is None and axis_idx is not None and t.value.get()) else None
		lo, hi = self._weight_limits()
		with _Batch(font):
			m, done = embolden_master(font, src, add_x, add_y, target, name, axis_idx, axis_value,
				bool(t.keepSB.get()), bool(t.keepH.get()), glyphs, bool(t.kern.get()),
				self._weight_mode(), lo, hi)
		t.status.set("Master %s: %d glyph diubah. Cek di tab Titik > Cek seluruh font." % (m.name, done))
		self._changed()


class VariableStudio(GeneralPlugin):

	@objc.python_method
	def settings(self):
		self.name = "Variable Studio"

	@objc.python_method
	def start(self):
		item = NSMenuItem.alloc().initWithTitle_action_keyEquivalent_(self.name, self.showWindow_, "")
		item.setTarget_(self)
		Glyphs.menu[WINDOW_MENU].append(item)

	def showWindow_(self, sender):
		try:
			self.controller = VariableStudioWindow()
		except Exception:
			print(traceback.format_exc())

	@objc.python_method
	def __file__(self):
		"""Please leave this method unchanged"""
		return __file__
