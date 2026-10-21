# encoding: utf-8
# Variable Studio Preview - reporter (View > Show Variable Studio):
# ID titik "kontur:indeks", bayangan semua master, jalur titik antar master,
# dan bentuk hasil interpolasi di lokasi yang dipilih di jendela Variable Studio.

from __future__ import division, print_function, unicode_literals
import objc
from GlyphsApp import Glyphs
from GlyphsApp.plugins import ReporterPlugin
from AppKit import NSColor, NSBezierPath, NSPoint, NSRect
from vs_core import *  # BUILD:STRIP
from vs_glyphs import *  # BUILD:STRIP
from vs_core import _xy  # BUILD:STRIP
from vs_glyphs import _decomposed  # BUILD:STRIP

GHOST_COLORS = [
	(0.10, 0.75, 0.85), (0.95, 0.55, 0.20), (0.40, 0.80, 0.35), (0.35, 0.45, 0.95),
	(0.85, 0.35, 0.80), (0.90, 0.30, 0.30), (0.75, 0.70, 0.20), (0.55, 0.55, 0.60),
]


def _color(rgb, alpha=1.0):
	return NSColor.colorWithCalibratedRed_green_blue_alpha_(rgb[0], rgb[1], rgb[2], alpha)


def bezier_from_paths(paths):
	"""paths: [(closed, types, pts)] -> NSBezierPath."""
	bp = NSBezierPath.bezierPath()
	for closed, types, pts in paths:
		cur = None
		for op in path_segments(list(types), list(pts), closed):
			kind = op[0]
			if kind == "move":
				bp.moveToPoint_(NSPoint(*op[1]))
				cur = op[1]
			elif kind == "line":
				bp.lineToPoint_(NSPoint(*op[1]))
				cur = op[1]
			elif kind == "curve":
				bp.curveToPoint_controlPoint1_controlPoint2_(NSPoint(*op[3]), NSPoint(*op[1]), NSPoint(*op[2]))
				cur = op[3]
			elif kind == "qcurve":
				for c1, c2, end in quad_to_cubic(cur, op[1], op[2]):
					bp.curveToPoint_controlPoint1_controlPoint2_(NSPoint(*end), NSPoint(*c1), NSPoint(*c2))
				cur = op[2]
			elif kind == "close":
				bp.closePath()
	return bp


def _layer_paths(layer):
	out = []
	for p in layer.paths:
		out.append((bool(getattr(p, "closed", True)), [node_type(n) for n in p.nodes], [_xy(n) for n in p.nodes]))
	return out


class VariableStudioReporter(ReporterPlugin):

	@objc.python_method
	def settings(self):
		self.menuName = "Variable Studio"

	@objc.python_method
	def _font(self, layer):
		try:
			return layer.parent.parent
		except Exception:
			return None

	@objc.python_method
	def _interpolated(self, layer):
		if not pref_bool("previewOn", False):
			return None
		font = self._font(layer)
		if font is None or not len(font.axes):
			return None
		loc = preview_location()
		if len(loc) != len(font.axes):
			loc = axis_values(origin_master(font), font)
		data, _ = interpolate_glyph(layer.parent, font, loc)
		return data

	@objc.python_method
	def background(self, layer):
		font = self._font(layer)
		if font is None:
			return
		scale = self.getScale() or 1.0
		glyph = layer.parent
		srcs = glyph_sources(glyph, font, include_brace=pref_bool("showBrace", True))
		if pref_bool("showGhosts", True) and len(srcs) > 1:
			for k, (name, vals, l) in enumerate(srcs):
				if l is layer or getattr(l, "layerId", None) == getattr(layer, "layerId", 0):
					continue
				bp = bezier_from_paths(_layer_paths(_decomposed(l)))
				bp.setLineWidth_(1.0 / scale)
				bp.setLineDash_count_phase_([3.0 / scale, 3.0 / scale], 2, 0)
				_color(GHOST_COLORS[k % len(GHOST_COLORS)], 0.8).set()
				bp.stroke()
		if pref_bool("showTrails", True) and len(srcs) > 1:
			self._draw_trails(srcs, scale)
		data = self._interpolated(layer)
		if data:
			NSColor.colorWithCalibratedRed_green_blue_alpha_(0.45, 0.50, 0.58, 0.55).set()
			bezier_from_paths(data["paths"]).fill()

	@objc.python_method
	def _draw_trails(self, srcs, scale):
		"""Garis putus dari titik ke titik pasangannya di master lain (urut
		menurut nilai axis pertama), untuk melihat arah gerak saat interpolasi."""
		ordered = sorted(srcs, key=lambda s: tuple(s[1]))
		layers = [_layer_paths(s[2]) for s in ordered]
		ref = layers[0]
		_color((0.6, 0.65, 0.75), 0.6).set()
		for ci, (closed, types, pts) in enumerate(ref):
			for ni in range(len(pts)):
				chain = []
				for lp in layers:
					if ci < len(lp) and ni < len(lp[ci][2]) and lp[ci][1] == types:
						chain.append(lp[ci][2][ni])
				if len(chain) < 2:
					continue
				bp = NSBezierPath.bezierPath()
				bp.moveToPoint_(NSPoint(*chain[0]))
				for p in chain[1:]:
					bp.lineToPoint_(NSPoint(*p))
				bp.setLineWidth_(0.75 / scale)
				bp.setLineDash_count_phase_([1.0 / scale, 2.5 / scale], 2, 0)
				bp.stroke()

	@objc.python_method
	def _geometry(self, layer):
		"""Kontur (komponen dipecah) + hasil ukur, di-cache selama bentuk sama."""
		paths = layer_paths(_decomposed(layer))
		key = (id(layer), tuple((c, tuple(t), tuple(p)) for c, t, p in paths))
		cache = getattr(self, "_geo_cache", None)
		if cache is None or cache[0] != key:
			polys, roles = flatten_contours(paths)
			cache = (key, paths, polys, roles, measure_paths(paths) if paths else {"samples": []})
			self._geo_cache = cache
		return cache[1], cache[2], cache[3], cache[4]

	@objc.python_method
	def _pill(self, text, x, y, scale, ink=True):
		"""Kotak angka seperti alat ukur Glyphs."""
		fs = 10.0
		w = (len(text) * fs * 0.62 + 10.0) / scale
		h = (fs + 6.0) / scale
		if ink:
			NSColor.colorWithCalibratedWhite_alpha_(1.0, 0.92).set()
			color = NSColor.colorWithCalibratedWhite_alpha_(0.1, 1.0)
		else:
			NSColor.colorWithCalibratedRed_green_blue_alpha_(0.20, 0.45, 0.85, 0.85).set()
			color = NSColor.colorWithCalibratedWhite_alpha_(1.0, 1.0)
		NSBezierPath.bezierPathWithRoundedRect_xRadius_yRadius_(NSRect((x - w / 2.0, y - h / 2.0), (w, h)), h / 2.0, h / 2.0).fill()
		self.drawTextAtPoint(text, NSPoint(x, y), fontSize=fs, fontColor=color, align="center")

	@objc.python_method
	def _metrics_for(self, layer):
		font = self._font(layer)
		mid = getattr(layer, "associatedMasterId", None)
		for m in (font.masters if font is not None else []):
			if m.id == mid:
				out = {}
				for key, attr in (("x", "xHeight"), ("H", "capHeight"), ("a", "ascender"), ("d", "descender")):
					try:
						out[key] = float(getattr(m, attr))
					except Exception:
						pass
				return out
		return {"x": 500.0, "H": 700.0, "a": 800.0, "d": -200.0}

	@objc.python_method
	def _draw_widths(self, layer, scale):
		paths, polys, roles, measure = self._geometry(layer)
		if not polys:
			return
		line_color = NSColor.colorWithCalibratedRed_green_blue_alpha_(0.20, 0.45, 0.85, 0.9)
		gaps = pref_bool("showGaps", True)
		xs = [p[0] for poly in polys for p in poly]
		ys = [p[1] for poly in polys for p in poly]
		width = float(layer.width)
		lines = []
		if pref_bool("showScanH", False):
			try:
				heights = parse_heights(pref_get("scanHeights", "x/2, H/2"), self._metrics_for(layer))
			except Exception:
				heights = []
			for y in heights:
				if min(ys) < y < max(ys):
					lines.append((False, y))
		if pref_bool("showScanV", False):
			lines.append((True, (min(xs) + max(xs)) / 2.0))
		pills = []
		for vertical, v in lines:
			runs = scanline_runs(polys, roles, v, vertical)
			if not runs:
				continue
			lo, hi = (min(ys), max(ys)) if vertical else (min(0.0, min(xs)), max(width, max(xs)))
			line_color.set()
			bp = NSBezierPath.bezierPath()
			bp.moveToPoint_(NSPoint(v, lo) if vertical else NSPoint(lo, v))
			bp.lineToPoint_(NSPoint(v, hi) if vertical else NSPoint(hi, v))
			bp.setLineWidth_(1.0 / scale)
			bp.setLineDash_count_phase_([4.0 / scale, 3.0 / scale], 2, 0)
			bp.stroke()
			pieces = list(runs)
			if gaps and not vertical:
				pieces = [(0.0, runs[0][0], False)] + pieces + [(runs[-1][1], width, False)]
			for a, b, on in pieces:
				if (not on and not gaps) or abs(b - a) < 0.5:
					continue
				c = (a + b) / 2.0
				pills.append(((v, c) if vertical else (c, v), fmt_num(round(b - a)), on))
		if pref_bool("showWidthLabels", False):
			# label goresan menghindari kotak angka garis ukur
			for mx, my, sm in label_positions(measure["samples"], 34.0 / scale, [p[0] for p in pills]):
				line_color.set()
				bp = NSBezierPath.bezierPath()
				bp.moveToPoint_(NSPoint(*sm["pt"]))
				bp.lineToPoint_(NSPoint(*sm["end"]))
				bp.setLineWidth_(1.0 / scale)
				bp.stroke()
				self._pill(fmt_num(round(sm["th"])), mx, my, scale)
		for (x, y), text, on in pills:
			self._pill(text, x, y, scale, ink=on)

	@objc.python_method
	def _draw_thickness(self, layer, scale):
		"""Garis ukur tebal di tiap segmen: biru = tipis, merah = tebal; angka
		di garis tertipis dan tertebal."""
		try:
			m = measure_paths(layer_paths(layer))
		except Exception:
			return
		smp = m["samples"]
		if not smp:
			return
		lo = min(x["th"] for x in smp)
		hi = max(x["th"] for x in smp)
		for x in smp:
			f = 0.0 if hi <= lo else (x["th"] - lo) / (hi - lo)
			_color((0.15 + 0.8 * f, 0.35, 0.95 - 0.8 * f), 0.7).set()
			bp = NSBezierPath.bezierPath()
			bp.moveToPoint_(NSPoint(*x["pt"]))
			bp.lineToPoint_(NSPoint(*x["end"]))
			bp.setLineWidth_(1.0 / scale)
			bp.stroke()
		for x, rgb in ((min(smp, key=lambda q: q["th"]), (0.15, 0.35, 0.95)), (max(smp, key=lambda q: q["th"]), (0.95, 0.35, 0.15))):
			mx = (x["pt"][0] + x["end"][0]) / 2.0
			my = (x["pt"][1] + x["end"][1]) / 2.0
			self.drawTextAtPoint(fmt_num(round(x["th"])), NSPoint(mx, my), fontSize=11.0, fontColor=_color(rgb), align="center")

	@objc.python_method
	def foreground(self, layer):
		scale = self.getScale() or 1.0
		data = self._interpolated(layer)
		if data:
			r = 3.0 / scale
			for closed, types, pts in data["paths"]:
				n = len(pts)
				for i, (t, p) in enumerate(zip(types, pts)):
					if t == "offcurve":
						NSColor.colorWithCalibratedWhite_alpha_(0.7, 0.8).set()
						for j in (i - 1, (i + 1) % n):
							if types[j] != "offcurve":
								h = NSBezierPath.bezierPath()
								h.moveToPoint_(NSPoint(*p))
								h.lineToPoint_(NSPoint(*pts[j]))
								h.setLineWidth_(0.5 / scale)
								h.stroke()
						rr = r * 0.7
					else:
						_color((0.95, 0.60, 0.25)).set()
						rr = r
					NSBezierPath.bezierPathWithOvalInRect_(NSRect((p[0] - rr, p[1] - rr), (2 * rr, 2 * rr))).fill()
		if pref_bool("showThickness", False):
			self._draw_thickness(layer, scale)
		if pref_bool("showWidthLabels", False) or pref_bool("showScanH", False) or pref_bool("showScanV", False):
			self._draw_widths(layer, scale)
		if pref_bool("showIDs", True):
			color = NSColor.colorWithCalibratedRed_green_blue_alpha_(0.10, 0.65, 0.80, 1.0)
			off = 4.0 / scale
			for ci, p in enumerate(layer.paths):
				for ni, n in enumerate(p.nodes):
					if node_type(n) == "offcurve" and not pref_bool("idsOffcurve", False):
						continue
					x, y = _xy(n)
					self.drawTextAtPoint("%d:%d" % (ci, ni), NSPoint(x + off, y + off), fontSize=9.0, fontColor=color)

	@objc.python_method
	def __file__(self):
		"""Please leave this method unchanged"""
		return __file__
