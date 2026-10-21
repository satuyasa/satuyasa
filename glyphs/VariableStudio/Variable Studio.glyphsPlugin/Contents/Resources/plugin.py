# encoding: utf-8
# DIBUAT OLEH src/build.py - jangan edit di sini, edit src/*.py

# ======================================================================
# vs_core.py
# Variable Studio - inti tanpa Glyphs: model interpolasi, pembaca titik,
# pemeriksa kompatibilitas, perencana style. Semua fungsi di sini bisa dites
# tanpa Glyphs (duck typing pada objek layer/path/node).

import math

VS_VERSION = "1.0.0"
DEFAULTS_PREFIX = "com.satuyasa.VariableStudio."

# ---------------------------------------------------------------------------
# Preset

WEIGHT_PRESETS = [
	("Thin", 100), ("ExtraLight", 200), ("Light", 300), ("Regular", 400),
	("Medium", 500), ("SemiBold", 600), ("Bold", 700), ("ExtraBold", 800),
	("Black", 900),
]

WIDTH_PRESETS = [
	("UltraCondensed", 50), ("ExtraCondensed", 62.5), ("Condensed", 75),
	("SemiCondensed", 87.5), ("Normal", 100), ("SemiExpanded", 112.5),
	("Expanded", 125), ("ExtraExpanded", 150), ("UltraExpanded", 200),
]
WIDTH_CLASS_VALUES = [50, 62.5, 75, 87.5, 100, 112.5, 125, 150, 200]

# (label UI, nama axis, tag, nilai bawaan untuk master yang sudah ada)
AXIS_PRESETS = [
	("Weight (wght)", "Weight", "wght", 400),
	("Width (wdth)", "Width", "wdth", 100),
	("Italic (ital)", "Italic", "ital", 0),
	("Slant (slnt)", "Slant", "slnt", 0),
	("Optical Size (opsz)", "Optical Size", "opsz", 12),
	("Grade (GRAD)", "Grade", "GRAD", 0),
	("Custom", "", "", 0),
]

# Indeks warna glyph di Glyphs (glyph.color)
GLYPH_COLORS = [
	("Merah", 0), ("Oranye", 1), ("Cokelat", 2), ("Kuning", 3),
	("Hijau muda", 4), ("Hijau tua", 5), ("Biru muda", 6), ("Biru tua", 7),
	("Ungu", 8), ("Magenta", 9), ("Abu-abu", 10), ("Tanpa warna", None),
]

ON_CURVE = ("line", "curve", "qcurve", "hobbycurve")


def fmt_num(v):
	"""400.0 -> '400', 87.5 -> '87.5'."""
	v = float(v)
	if abs(v - round(v)) < 1e-9:
		return str(int(round(v)))
	return ("%.3f" % v).rstrip("0").rstrip(".")


def parse_style_list(text):
	"""'Thin=100\\nBold = 700' -> [('Thin', 100.0), ('Bold', 700.0)].
	Baris kosong dan baris diawali # diabaikan. Pemisah '=' atau ':'."""
	out = []
	for raw in (text or "").replace(",", "\n").splitlines():
		line = raw.strip()
		if not line or line.startswith("#"):
			continue
		sep = "=" if "=" in line else (":" if ":" in line else None)
		if sep is None:
			raise ValueError("Baris tanpa nilai: %r (pakai Nama=nilai)" % line)
		name, val = line.split(sep, 1)
		name = name.strip()
		if not name:
			raise ValueError("Baris tanpa nama: %r" % line)
		out.append((name, float(val.strip().replace(" ", ""))))
	return out


def style_list_text(pairs):
	return "\n".join("%s=%s" % (n, fmt_num(v)) for n, v in pairs)


# ---------------------------------------------------------------------------
# Pembaca titik

def _xy(node):
	p = node.position
	return float(p.x), float(p.y)


def node_type(node):
	t = node.type
	return str(t).lower() if t is not None else "line"


def read_layer_points(layer, selected_only=False):
	"""Daftar titik layer: dict id 'kontur:indeks' (sama dengan label di
	pratinjau), tipe, halus, x, y."""
	rows = []
	sel = None
	if selected_only:
		try:
			sel = set(id(n) for n in layer.selection)
		except Exception:
			sel = None
	for ci, path in enumerate(layer.paths):
		for ni, node in enumerate(path.nodes):
			if sel is not None and id(node) not in sel and not getattr(node, "selected", False):
				continue
			x, y = _xy(node)
			rows.append({
				"id": "%d:%d" % (ci, ni), "contour": ci, "index": ni,
				"type": node_type(node), "smooth": bool(getattr(node, "smooth", False)),
				"x": x, "y": y,
			})
	return rows


def points_to_csv(rows_by_master, master_names):
	"""rows_by_master: list (per master) of read_layer_points rows."""
	head = ["id", "type", "smooth"]
	for n in master_names:
		head += ["%s x" % n, "%s y" % n]
	lines = [",".join('"%s"' % h for h in head)]
	base = rows_by_master[0] if rows_by_master else []
	for i, r in enumerate(base):
		cells = [r["id"], r["type"], "1" if r["smooth"] else "0"]
		for rows in rows_by_master:
			if i < len(rows):
				cells += [fmt_num(rows[i]["x"]), fmt_num(rows[i]["y"])]
			else:
				cells += ["", ""]
		lines.append(",".join(cells))
	return "\n".join(lines) + "\n"


# ---------------------------------------------------------------------------
# Kompatibilitas

def path_signature(path):
	return (bool(getattr(path, "closed", True)), tuple(node_type(n) for n in path.nodes))


def layer_signature(layer):
	comps = []
	for c in getattr(layer, "components", []) or []:
		comps.append(getattr(c, "componentName", None) or getattr(c, "name", ""))
	anchors = sorted(a.name for a in (getattr(layer, "anchors", []) or []))
	return {
		"paths": [path_signature(p) for p in layer.paths],
		"components": comps,
		"anchors": anchors,
	}


def signed_area(points):
	a = 0.0
	n = len(points)
	for i in range(n):
		x1, y1 = points[i]
		x2, y2 = points[(i + 1) % n]
		a += x1 * y2 - x2 * y1
	return a / 2.0


def path_direction(path):
	"""1 = berlawanan jarum jam, -1 = searah, 0 = datar."""
	a = signed_area([_xy(n) for n in path.nodes])
	return 0 if abs(a) < 1e-9 else (1 if a > 0 else -1)


def compare_signatures(ref, sig, ref_name="ref", name="master"):
	"""Daftar masalah (teks) antara dua signature layer."""
	issues = []
	if len(ref["paths"]) != len(sig["paths"]):
		issues.append("%s: jumlah path %d, %s: %d" % (ref_name, len(ref["paths"]), name, len(sig["paths"])))
	else:
		for ci, (a, b) in enumerate(zip(ref["paths"], sig["paths"])):
			if a[0] != b[0]:
				issues.append("path %d: terbuka/tertutup beda di %s" % (ci, name))
			if len(a[1]) != len(b[1]):
				issues.append("path %d: %d titik di %s, %d di %s" % (ci, len(a[1]), ref_name, len(b[1]), name))
			elif a[1] != b[1]:
				k = next(i for i in range(len(a[1])) if a[1][i] != b[1][i])
				if rotation_candidates(a[1], b[1]):
					issues.append("path %d: titik awal beda di %s (mulai %d:%d)" % (ci, name, ci, k))
				else:
					issues.append("path %d: tipe titik %d:%d beda (%s vs %s) di %s" % (ci, ci, k, a[1][k], b[1][k], name))
	if ref["components"] != sig["components"]:
		if sorted(ref["components"]) == sorted(sig["components"]):
			issues.append("urutan komponen beda di %s" % name)
		else:
			issues.append("komponen beda di %s: %s vs %s" % (name, ", ".join(ref["components"]) or "-", ", ".join(sig["components"]) or "-"))
	if ref["anchors"] != sig["anchors"]:
		missing = sorted(set(ref["anchors"]) - set(sig["anchors"]))
		extra = sorted(set(sig["anchors"]) - set(ref["anchors"]))
		parts = []
		if missing:
			parts.append("tidak ada " + ", ".join(missing))
		if extra:
			parts.append("lebih " + ", ".join(extra))
		issues.append("anchor beda di %s: %s" % (name, "; ".join(parts)))
	return issues


def rotation_candidates(ref_types, types):
	"""Semua r sehingga types[(i+r)%n] == ref_types[i] untuk semua i."""
	n = len(ref_types)
	if n != len(types) or n == 0:
		return []
	out = []
	for r in range(n):
		if all(types[(i + r) % n] == ref_types[i] for i in range(n)):
			out.append(r)
	return out


def _normalized(points):
	xs = [p[0] for p in points]
	ys = [p[1] for p in points]
	w = (max(xs) - min(xs)) or 1.0
	h = (max(ys) - min(ys)) or 1.0
	return [((x - min(xs)) / w, (y - min(ys)) / h) for x, y in points]


def best_rotation(ref_types, ref_pts, types, pts):
	"""Rotasi titik awal terbaik (posisi relatif terhadap bbox path paling
	dekat). None bila tipe titik tidak bisa dicocokkan."""
	cands = rotation_candidates(ref_types, types)
	if not cands:
		return None
	a = _normalized(ref_pts)
	b = _normalized(pts)
	n = len(a)
	best, best_d = None, None
	for r in cands:
		d = sum((a[i][0] - b[(i + r) % n][0]) ** 2 + (a[i][1] - b[(i + r) % n][1]) ** 2 for i in range(n))
		if best_d is None or d < best_d - 1e-12:
			best, best_d = r, d
	return best


# ---------------------------------------------------------------------------
# Model variasi (port ringkas fontTools.varLib.models.VariationModel)

def normalize_value(v, triple):
	lo, df, hi = triple
	v = max(min(v, hi), lo)
	if v == df:
		return 0.0
	if v < df:
		return 0.0 if df == lo else (v - df) / (df - lo)
	return 0.0 if hi == df else (v - df) / (hi - df)


def normalize_location(values, triples):
	return dict((i, normalize_value(v, t)) for i, (v, t) in enumerate(zip(values, triples)))


def support_scalar(loc, support):
	scalar = 1.0
	for axis, (lower, peak, upper) in support.items():
		if peak == 0.0:
			continue
		if lower > peak or peak > upper:
			continue
		if lower < 0.0 and upper > 0.0:
			continue
		v = loc.get(axis, 0.0)
		if v == peak:
			continue
		if v <= lower or upper <= v:
			return 0.0
		if v < peak:
			scalar *= (v - lower) / (peak - lower)
		else:
			scalar *= (v - upper) / (peak - upper)
	return scalar


def _sort_key_func(locations, axis_order):
	axis_points = {}
	for loc in locations:
		if len(loc) != 1:
			continue
		axis = next(iter(loc))
		axis_points.setdefault(axis, set([0.0])).add(loc[axis])

	def sign(v):
		return -1 if v < 0 else (1 if v > 0 else 0)

	def key(loc):
		on_point = [a for a, v in loc.items() if a in axis_points and v in axis_points[a]]
		ordered = [a for a in axis_order if a in loc]
		ordered += [a for a in sorted(loc.keys()) if a not in axis_order]
		return (
			len(loc), -len(on_point),
			tuple(axis_order.index(a) if a in axis_order else 0x10000 for a in ordered),
			tuple(ordered),
			tuple(sign(loc[a]) for a in ordered),
			tuple(abs(loc[a]) for a in ordered),
		)
	return key


class VariationModel(object):
	"""locations: list dict {axis: nilai ternormalisasi}; harus ada lokasi {}
	(semua nol) sebagai master default."""

	def __init__(self, locations, axis_order=None):
		locs = [dict((k, v) for k, v in l.items() if v != 0.0) for l in locations]
		keys = [tuple(sorted(l.items())) for l in locs]
		if len(set(keys)) != len(keys):
			raise ValueError("Lokasi master ganda")
		if () not in keys:
			raise ValueError("Tidak ada master di lokasi default (origin)")
		axis_order = list(axis_order or [])
		self.locations = sorted(locs, key=_sort_key_func(locs, axis_order))
		skeys = [tuple(sorted(l.items())) for l in self.locations]
		self.reverse_mapping = [keys.index(k) for k in skeys]
		self._compute_supports()

	def _regions(self):
		min_v, max_v = {}, {}
		for l in self.locations:
			for k, v in l.items():
				min_v[k] = min(v, min_v.get(k, v))
				max_v[k] = max(v, max_v.get(k, v))
		regions = []
		for loc in self.locations:
			region = {}
			for axis, v in loc.items():
				region[axis] = (0, v, max_v[axis]) if v > 0 else (min_v[axis], v, 0)
			regions.append(region)
		return regions

	def _compute_supports(self):
		self.supports = []
		regions = self._regions()
		for i, region in enumerate(regions):
			loc_axes = set(region.keys())
			for prev in regions[:i]:
				if set(prev.keys()) != loc_axes:
					continue
				relevant = True
				for axis, (lower, peak, upper) in region.items():
					pv = prev[axis][1]
					if not (pv == peak or lower < pv < upper):
						relevant = False
						break
				if not relevant:
					continue
				best_axes, best_ratio = {}, -1
				for axis in prev.keys():
					val = prev[axis][1]
					lower, loc_v, upper = region[axis]
					new_lower, new_upper = lower, upper
					if val < loc_v:
						new_lower = val
						ratio = (val - loc_v) / (lower - loc_v)
					elif loc_v < val:
						new_upper = val
						ratio = (val - loc_v) / (upper - loc_v)
					else:
						continue
					if ratio > best_ratio:
						best_axes, best_ratio = {}, ratio
					if ratio == best_ratio:
						best_axes[axis] = (new_lower, loc_v, new_upper)
				for axis, triple in best_axes.items():
					region[axis] = triple
			self.supports.append(region)
		self.delta_weights = []
		for i, loc in enumerate(self.locations):
			dw = {}
			for j, support in enumerate(self.supports[:i]):
				s = support_scalar(loc, support)
				if s:
					dw[j] = s
			self.delta_weights.append(dw)

	def deltas(self, master_values):
		"""master_values: list vektor (list float), urut sesuai lokasi asli."""
		out = []
		for i, weights in enumerate(self.delta_weights):
			d = list(master_values[self.reverse_mapping[i]])
			for j, w in weights.items():
				oj = out[j]
				for k in range(len(d)):
					d[k] -= oj[k] * w
			out.append(d)
		return out

	def scalars(self, loc):
		return [support_scalar(loc, s) for s in self.supports]

	def interpolate(self, master_values, loc, deltas=None):
		deltas = deltas if deltas is not None else self.deltas(master_values)
		res = [0.0] * len(deltas[0])
		for d, s in zip(deltas, self.scalars(loc)):
			if not s:
				continue
			for k in range(len(res)):
				res[k] += d[k] * s
		return res


def lerp_vectors(a, b, t):
	return [x + (y - x) * t for x, y in zip(a, b)]


# ---------------------------------------------------------------------------
# Segmen untuk digambar (NSBezierPath) dari daftar node

def path_segments(types, pts, closed=True):
	"""Ubah node gaya Glyphs (closed: node terakhir = titik awal) jadi
	operasi gambar: ('move', p), ('line', p), ('curve', c1, c2, p),
	('qcurve', [offs], p), ('close',)."""
	n = len(types)
	if n == 0:
		return []
	ops = []
	if closed:
		start = n - 1
		for k in range(n):
			if types[(n - 1 - k) % n] != "offcurve":
				start = (n - 1 - k) % n
				break
		order = [(start + 1 + k) % n for k in range(n)]
		ops.append(("move", pts[start]))
	else:
		order = list(range(1, n))
		ops.append(("move", pts[0]))
	offs = []
	for i in order:
		t = types[i]
		if t == "offcurve":
			offs.append(pts[i])
			continue
		if t == "line" or not offs:
			ops.append(("line", pts[i]))
		elif t == "qcurve":
			ops.append(("qcurve", list(offs), pts[i]))
		elif len(offs) == 2:
			ops.append(("curve", offs[0], offs[1], pts[i]))
		elif len(offs) == 1:
			ops.append(("qcurve", list(offs), pts[i]))
		else:
			ops.append(("curve", offs[0], offs[-1], pts[i]))
		offs = []
	if closed:
		ops.append(("close",))
	return ops


def quad_to_cubic(p0, offs, p):
	"""Pecah kurva kuadratik (TrueType, bisa beberapa off-curve) jadi kubik."""
	out = []
	cur = p0
	for k, q in enumerate(offs):
		end = p if k == len(offs) - 1 else ((q[0] + offs[k + 1][0]) / 2.0, (q[1] + offs[k + 1][1]) / 2.0)
		c1 = (cur[0] + 2.0 / 3 * (q[0] - cur[0]), cur[1] + 2.0 / 3 * (q[1] - cur[1]))
		c2 = (end[0] + 2.0 / 3 * (q[0] - end[0]), end[1] + 2.0 / 3 * (q[1] - end[1]))
		out.append((c1, c2, end))
		cur = end
	return out


# ---------------------------------------------------------------------------
# Pemetaan user <-> desain

def piecewise(v, pairs):
	"""pairs: [(input, output)]. Interpolasi linear antar pasangan,
	ekstrapolasi dari dua pasangan di ujung."""
	pairs = sorted(pairs)
	if not pairs:
		return v
	if len(pairs) == 1:
		return pairs[0][1] + (v - pairs[0][0])
	if v <= pairs[0][0]:
		(a, fa), (b, fb) = pairs[0], pairs[1]
	elif v >= pairs[-1][0]:
		(a, fa), (b, fb) = pairs[-2], pairs[-1]
	else:
		(a, fa), (b, fb) = next((p, q) for p, q in zip(pairs, pairs[1:]) if p[0] <= v <= q[0])
	if b == a:
		return fa
	return fa + (fb - fa) * (v - a) / float(b - a)


MAPPING_MODES = [
	("linear", "Linear (rentang master)"),
	("geometric", "Geometris / Lucas de Groot"),
	("identity", "Desain = user (tanpa pemetaan)"),
	("axislocation", "Dari Axis Location master"),
]


def design_values(user_values, dmin, dmax, mode="linear", master_pairs=None):
	"""Petakan nilai user (mis. 100..900) ke koordinat desain master.
	master_pairs: [(user, design)] dari Axis Location master."""
	if not user_values:
		return []
	if mode == "identity":
		return [float(u) for u in user_values]
	if dmin == dmax:
		# semua master di posisi yang sama pada axis ini: tidak ada rentang
		return [float(dmin)] * len(user_values)
	if mode == "axislocation":
		if not master_pairs or len(master_pairs) < 2:
			raise ValueError("Master belum punya Axis Location untuk axis ini")
		return [piecewise(u, master_pairs) for u in user_values]
	umin, umax = min(user_values), max(user_values)
	out = []
	for u in user_values:
		t = 0.0 if umax == umin else (u - umin) / float(umax - umin)
		if mode == "geometric" and dmin > 0 and dmax > 0:
			out.append(dmin * (dmax / float(dmin)) ** t)
		else:
			out.append(dmin + (dmax - dmin) * t)
	return out


# ---------------------------------------------------------------------------
# Perencana instance

def width_class_for(user_width):
	best = min(range(len(WIDTH_CLASS_VALUES)), key=lambda i: abs(WIDTH_CLASS_VALUES[i] - user_width))
	return best + 1


def _join(*parts):
	return " ".join(p for p in parts if p)


def instance_name(weight_name, width_name, italic, regular_name="Regular", normal_name="Normal", italic_name="Italic"):
	w = "" if weight_name == regular_name else weight_name
	d = "" if width_name in (normal_name, None, "") else width_name
	name = _join(d, w, italic_name if italic else "")
	return name or regular_name


def build_instance_plan(weights, widths=None, upright=True, italic=False,
		regular_name="Regular", bold_name="Bold", normal_name="Normal", italic_name="Italic"):
	"""weights/widths: [(nama, nilai user)]. Hasil: list dict siap dibuat
	jadi GSInstance (urut lebar -> tebal -> tegak/miring)."""
	widths = widths or [(normal_name, 100.0)]
	styles = []
	if upright:
		styles.append(False)
	if italic:
		styles.append(True)
	weight_names = [n for n, _ in weights]
	has_regular = regular_name in weight_names
	plan = []
	for wd_name, wd_val in widths:
		base_name = instance_name(regular_name, wd_name, False, regular_name, normal_name, italic_name)
		for wt_name, wt_val in weights:
			for it in styles:
				name = instance_name(wt_name, wd_name, it, regular_name, normal_name, italic_name)
				upright_name = instance_name(wt_name, wd_name, False, regular_name, normal_name, italic_name)
				is_bold = has_regular and wt_name == bold_name
				if is_bold:
					link = base_name
				elif it:
					link = upright_name
				else:
					link = ""
				# Untuk Regular tegak di lebar Normal, linkStyle kosong.
				if link == name:
					link = ""
				plan.append({
					"name": name, "weight": float(wt_val), "width": float(wd_val), "italic": it,
					"weight_name": wt_name, "width_name": wd_name,
					"isBold": bool(is_bold), "isItalic": bool(it), "linkStyle": link,
					"weightClass": int(max(1, min(1000, round(wt_val)))),
					"widthClass": width_class_for(wd_val),
					"elidable_weight": wt_name == regular_name,
					"elidable_width": wd_name == normal_name,
				})
	return plan


# ---------------------------------------------------------------------------
# Miring (italic)

def slant_transform(angle_deg, origin_y=0.0, compensate=False, center_x=0.0):
	"""NSAffineTransformStruct (m11, m12, m21, m22, tX, tY) untuk memiringkan.
	compensate: lebarkan horizontal 1/cos(sudut) agar tebal stem tegak lurus
	tetap sama seperti sebelum dimiringkan."""
	t = math.tan(math.radians(angle_deg))
	sx = 1.0 / math.cos(math.radians(angle_deg)) if compensate else 1.0
	return (sx, 0.0, t, 1.0, center_x * (1 - sx) - t * origin_y, 0.0)


def apply_affine(m, x, y):
	return (m[0] * x + m[2] * y + m[4], m[1] * x + m[3] * y + m[5])
# ======================================================================
# vs_glyphs.py
# Variable Studio - operasi pada font Glyphs 3 (axis, master, instance,
# italic, interpolasi, kompatibilitas). Dipakai oleh jendela dan reporter.

import copy
import json
import math
import uuid

from GlyphsApp import Glyphs, GSAxis, GSInstance, GSCustomParameter
from Foundation import NSPoint

INSTANCE_VARIABLE = 1  # INSTANCETYPEVARIABLE


# ---------------------------------------------------------------------------
# Pengaturan bersama jendela <-> reporter (Glyphs.defaults)

def pref_get(key, default=None):
	try:
		v = Glyphs.defaults[DEFAULTS_PREFIX + key]
	except Exception:
		v = None
	return default if v is None else v


def pref_set(key, value):
	Glyphs.defaults[DEFAULTS_PREFIX + key] = value


def pref_bool(key, default=False):
	v = pref_get(key, None)
	if v is None:
		return default
	try:
		return bool(int(v))
	except Exception:
		return bool(v)


def preview_location():
	try:
		return [float(v) for v in json.loads(pref_get("previewLoc", "[]"))]
	except Exception:
		return []


def set_preview_location(values):
	pref_set("previewLoc", json.dumps([float(v) for v in values]))


# ---------------------------------------------------------------------------
# Custom parameter

def cp_get(obj, key):
	try:
		return obj.customParameters[key]
	except Exception:
		return None


def cp_set(obj, key, value):
	obj.customParameters[key] = value


def cp_del(obj, key):
	try:
		while obj.customParameters[key] is not None:
			del obj.customParameters[key]
	except Exception:
		pass


def cp_append(obj, key, value):
	obj.customParameters.append(GSCustomParameter(key, value))


# ---------------------------------------------------------------------------
# Axis & lokasi

def axes_info(font):
	out = []
	for i, a in enumerate(font.axes):
		out.append({
			"index": i, "name": a.name, "tag": a.axisTag,
			"id": getattr(a, "axisId", None),
			"hidden": bool(getattr(a, "hidden", False)),
		})
	return out


def axis_index(font, tag):
	for i, a in enumerate(font.axes):
		if a.axisTag == tag:
			return i
	return None


def is_static(instance):
	return getattr(instance, "type", 0) != INSTANCE_VARIABLE


def axis_values(obj, font):
	n = len(font.axes)
	try:
		vals = [0.0 if v is None else float(v) for v in list(obj.axes)]
	except Exception:
		vals = []
	return (vals + [0.0] * n)[:n]


def set_axis_values(obj, font, values):
	values = [float(v) for v in values]
	try:
		obj.axes = values
	except Exception:
		for i, v in enumerate(values):
			obj.axes[i] = v


def set_axis_value(obj, font, idx, value):
	vals = axis_values(obj, font)
	vals[idx] = float(value)
	set_axis_values(obj, font, vals)


def origin_master(font):
	val = cp_get(font, "Variable Font Origin")
	if val:
		for m in font.masters:
			if m.id == val or m.name == val:
				return m
	return font.masters[0]


def axis_triples(font):
	"""(min, default, max) koordinat desain per axis, dari master."""
	origin = axis_values(origin_master(font), font)
	triples = []
	all_vals = [axis_values(m, font) for m in font.masters]
	for i in range(len(font.axes)):
		vs = [v[i] for v in all_vals]
		triples.append((min(vs), origin[i], max(vs)))
	return triples


def axis_location_dict(obj):
	loc = cp_get(obj, "Axis Location")
	out = {}
	for e in loc or []:
		try:
			out[str(e["Axis"])] = float(e["Location"])
		except Exception:
			pass
	return out


def set_axis_location(obj, font, by_name):
	cp_set(obj, "Axis Location", [
		{"Axis": a.name, "Location": by_name[a.name]} for a in font.axes if a.name in by_name])


def master_axis_pairs(font, idx):
	"""[(user, desain)] dari Axis Location master untuk axis idx."""
	name = font.axes[idx].name
	pairs = []
	for m in font.masters:
		loc = axis_location_dict(m)
		if name in loc:
			pairs.append((loc[name], axis_values(m, font)[idx]))
	return sorted(set(pairs))


def describe_location(font, values):
	return ", ".join("%s %s" % (a.axisTag, fmt_num(v)) for a, v in zip(font.axes, values))


# ---------------------------------------------------------------------------
# Sumber per glyph (master + brace layer)

def brace_coords(layer, font):
	try:
		if not layer.isSpecialLayer:
			return None
		c = layer.attributes["coordinates"]
	except Exception:
		return None
	if not c:
		return None
	vals = []
	for a in font.axes:
		try:
			v = c[a.axisId]
		except Exception:
			v = None
		if v is None:
			return None
		vals.append(float(v))
	return vals


def glyph_sources(glyph, font, include_brace=True):
	"""[(nama, nilai axis, layer)] untuk semua master (+ brace layer)."""
	out = []
	for m in font.masters:
		layer = glyph.layers[m.id]
		if layer is not None:
			out.append((m.name, axis_values(m, font), layer))
	if include_brace:
		for layer in glyph.layers:
			coords = brace_coords(layer, font)
			if coords:
				out.append(("{%s}" % ", ".join(fmt_num(c) for c in coords), coords, layer))
	return out


def _decomposed(layer):
	try:
		d = layer.copyDecomposedLayer()
		if d is not None:
			return d
	except Exception:
		pass
	return layer


def layer_vector(layer):
	"""(signature, vektor koordinat) dari layer yang komponennya dipecah."""
	d = _decomposed(layer)
	paths = []
	vec = []
	for p in d.paths:
		paths.append(path_signature(p))
		for n in p.nodes:
			x, y = _xy(n)
			vec.append(x)
			vec.append(y)
	anchors = sorted((a.name, a) for a in (getattr(d, "anchors", []) or []))
	for _, a in anchors:
		vec.append(float(a.position.x))
		vec.append(float(a.position.y))
	vec.append(float(d.width))
	return {"paths": paths, "anchors": [n for n, _ in anchors]}, vec


_MODEL_CACHE = {}


def model_for(locations_norm, n_axes):
	key = tuple(tuple(sorted(l.items())) for l in locations_norm)
	m = _MODEL_CACHE.get(key)
	if m is None:
		if len(_MODEL_CACHE) > 64:
			_MODEL_CACHE.clear()
		m = VariationModel(locations_norm, list(range(n_axes)))
		_MODEL_CACHE[key] = m
	return m


def interpolate_glyph(glyph, font, values):
	"""Bentuk glyph di lokasi desain `values`. Hasil (data, None) atau
	(None, alasan). data: paths [(closed, types, pts)], anchors, width."""
	srcs = glyph_sources(glyph, font)
	if not srcs:
		return None, "tidak ada layer"
	triples = axis_triples(font)
	sigs, vecs, locs, names, seen = [], [], [], [], set()
	for name, vals, layer in srcs:
		loc = normalize_location(vals, triples)
		key = tuple(sorted((k, v) for k, v in loc.items() if v))
		if key in seen:
			continue
		seen.add(key)
		sig, vec = layer_vector(layer)
		sigs.append(sig)
		vecs.append(vec)
		locs.append(loc)
		names.append(name)
	for sig, name in zip(sigs[1:], names[1:]):
		if sig != sigs[0]:
			return None, "tidak kompatibel (%s)" % name
	if len(vecs) == 1:
		res = vecs[0]
	else:
		try:
			model = model_for(locs, len(font.axes))
		except ValueError as e:
			return None, str(e)
		res = model.interpolate(vecs, normalize_location(values, triples))
	paths = []
	k = 0
	for closed, types in sigs[0]["paths"]:
		pts = []
		for _ in types:
			pts.append((res[k], res[k + 1]))
			k += 2
		paths.append((closed, types, pts))
	anchors = []
	for name in sigs[0]["anchors"]:
		anchors.append((name, (res[k], res[k + 1])))
		k += 2
	return {"paths": paths, "anchors": anchors, "width": res[k]}, None


# ---------------------------------------------------------------------------
# Kompatibilitas & perbaikan

def _types_pts(path):
	return [node_type(n) for n in path.nodes], [_xy(n) for n in path.nodes]


def check_glyph(glyph, font):
	srcs = glyph_sources(glyph, font)
	if len(srcs) < 2:
		return []
	ref_name, _, ref_layer = srcs[0]
	ref = layer_signature(ref_layer)
	issues = []
	for name, _, layer in srcs[1:]:
		issues += compare_signatures(ref, layer_signature(layer), ref_name, name)
		if len(layer.paths) == len(ref_layer.paths):
			for ci, (rp, p) in enumerate(zip(ref_layer.paths, layer.paths)):
				if len(rp.nodes) != len(p.nodes):
					continue
				rd, d = path_direction(rp), path_direction(p)
				if rd and d and rd != d:
					issues.append("path %d: arah beda di %s" % (ci, name))
	return issues


def scan_font(font, glyphs=None):
	out = []
	for g in (glyphs if glyphs is not None else font.glyphs):
		issues = check_glyph(g, font)
		if issues:
			out.append((g.name, issues))
	return out


def _rotate_path(path, r):
	"""Jadikan node old[r] posisi 0 (cara aman: coba makeNodeFirst pada
	tiap on-curve dan periksa hasilnya)."""
	old = [(node_type(n), _xy(n)) for n in path.nodes]
	n = len(old)
	want = [old[(i + r) % n] for i in range(n)]
	for cand in list(path.nodes):
		if node_type(cand) == "offcurve":
			continue
		cand.makeNodeFirst()
		if [(node_type(x), _xy(x)) for x in path.nodes] == want:
			return True
	for cand in list(path.nodes):
		if node_type(cand) == "offcurve":
			continue
		cand.makeNodeFirst()
		if [(node_type(x), _xy(x)) for x in path.nodes] == old:
			break
	return False


def fix_glyph(glyph, font):
	"""Samakan arah dan titik awal path semua master dengan master pertama."""
	srcs = glyph_sources(glyph, font)
	if len(srcs) < 2:
		return []
	ref_layer = srcs[0][2]
	changes = []
	for name, _, layer in srcs[1:]:
		if len(layer.paths) != len(ref_layer.paths):
			changes.append("%s: jumlah path beda, tidak diubah" % name)
			continue
		for ci, (rp, p) in enumerate(zip(ref_layer.paths, layer.paths)):
			if len(rp.nodes) != len(p.nodes) or not getattr(p, "closed", True):
				continue
			rd, d = path_direction(rp), path_direction(p)
			if rd and d and rd != d:
				p.reverse()
				changes.append("%s path %d: arah dibalik" % (name, ci))
			rt, rpts = _types_pts(rp)
			t, pts = _types_pts(p)
			r = best_rotation(rt, rpts, t, pts)
			if r is None:
				if t != rt:
					changes.append("%s path %d: tipe titik tidak bisa dicocokkan" % (name, ci))
				continue
			if r == 0:
				continue
			if _rotate_path(p, r):
				changes.append("%s path %d: titik awal digeser %d" % (name, ci, r))
			else:
				changes.append("%s path %d: gagal menggeser titik awal" % (name, ci))
	return changes


def set_glyph_color(glyph, color_index):
	if color_index is None:
		try:
			glyph.color = None
		except Exception:
			glyph.color = 9223372036854775807
	else:
		glyph.color = color_index


# ---------------------------------------------------------------------------
# Axis, origin, virtual master, variable setting

def add_axis(font, name, tag, value, hidden=False):
	tag = (tag or "").strip()
	name = (name or "").strip()
	if not name or not tag:
		raise ValueError("Nama dan tag axis wajib diisi")
	if len(tag) > 4:
		raise ValueError("Tag axis maksimal 4 huruf")
	for a in font.axes:
		if a.axisTag == tag:
			raise ValueError("Axis dengan tag %s sudah ada" % tag)
		if a.name == name:
			raise ValueError("Axis bernama %s sudah ada" % name)
	axis = GSAxis()
	axis.name = name
	axis.axisTag = tag
	if hidden:
		axis.hidden = True
	font.axes.append(axis)
	idx = len(font.axes) - 1
	targets = list(font.masters) + [i for i in font.instances if is_static(i)]
	for obj in targets:
		set_axis_value(obj, font, idx, value)
		loc = axis_location_dict(obj)
		if loc:
			loc[name] = float(value)
			set_axis_location(obj, font, loc)
	return axis


def set_origin(font, master):
	cp_set(font, "Variable Font Origin", master.id)


def add_variable_setting(font, name="Variable"):
	for i in font.instances:
		if not is_static(i) and i.name == name:
			return None
	inst = GSInstance(INSTANCE_VARIABLE)
	inst.name = name
	font.instances.append(inst)
	return inst


def add_virtual_master(font, values_by_name):
	cp_append(font, "Virtual Master", [{"Axis": n, "Location": float(v)} for n, v in values_by_name.items()])


# ---------------------------------------------------------------------------
# Membuat instance (Thin ... Black, lebar, italic)

def _italic_axis(font):
	for tag in ("ital", "slnt"):
		idx = axis_index(font, tag)
		if idx is not None:
			return idx, tag
	return None, None


def _italic_design(font, idx, tag, italic):
	vals = [axis_values(m, font)[idx] for m in font.masters]
	if tag == "ital":
		return max(vals) if italic else min(vals)
	# slnt: miring = nilai terjauh dari 0, tegak = nilai terdekat ke 0
	if italic:
		return max(vals, key=abs)
	return min(vals, key=abs)


def axis_user_to_design(font, idx, user_values, mode):
	"""{user: desain} untuk satu axis."""
	vals = [axis_values(m, font)[idx] for m in font.masters]
	users = sorted(set(float(u) for u in user_values))
	designs = design_values(users, min(vals), max(vals), mode, master_axis_pairs(font, idx))
	return dict(zip(users, designs))


def plan_with_design(font, plan, mode="linear"):
	"""Lengkapi rencana dengan koordinat desain & user per axis."""
	warnings = []
	n = len(font.axes)
	origin = axis_values(origin_master(font), font)
	origin_user = axis_location_dict(origin_master(font))
	wi, di = axis_index(font, "wght"), axis_index(font, "wdth")
	ii, itag = _italic_axis(font)
	weights = set(p["weight"] for p in plan)
	widths = set(p["width"] for p in plan)
	if wi is None and len(weights) > 1:
		raise ValueError("Font belum punya axis Weight (wght). Tambahkan di tab Axis.")
	if di is None and len(widths) > 1:
		raise ValueError("Font belum punya axis Width (wdth). Tambahkan di tab Axis.")
	if ii is None and any(p["italic"] for p in plan):
		warnings.append("Tidak ada axis ital/slnt: instance Italic hanya diberi tanda isItalic (untuk file italic terpisah).")
	wmap = axis_user_to_design(font, wi, weights, mode) if wi is not None else {}
	dmap = axis_user_to_design(font, di, widths, mode) if di is not None else {}
	out = []
	for p in plan:
		q = dict(p)
		design = list(origin)
		user = {}
		for k, a in enumerate(font.axes):
			user[a.name] = origin_user.get(a.name, origin[k])
		if wi is not None:
			design[wi] = wmap[p["weight"]]
			user[font.axes[wi].name] = p["weight"]
		if di is not None:
			design[di] = dmap[p["width"]]
			user[font.axes[di].name] = p["width"]
		if ii is not None:
			design[ii] = _italic_design(font, ii, itag, p["italic"])
			user[font.axes[ii].name] = design[ii] if itag == "slnt" else (1.0 if p["italic"] else 0.0)
		q["design"] = design[:n]
		q["user"] = user
		out.append(q)
	return out, warnings


def _master_user_values(font, planned):
	"""Nilai user tiap master per axis: dari Axis Location yang ada, kalau
	tidak ada dihitung balik dari pasangan (desain -> user) rencana."""
	res = {}
	for m in font.masters:
		loc = axis_location_dict(m)
		vals = axis_values(m, font)
		user = {}
		for k, a in enumerate(font.axes):
			if a.name in loc:
				user[a.name] = loc[a.name]
				continue
			pairs = sorted(set((p["design"][k], p["user"][a.name]) for p in planned))
			if a.axisTag in ("wght", "wdth") and len(pairs) >= 2:
				user[a.name] = round(piecewise(vals[k], pairs), 3)
			elif a.axisTag == "ital":
				lo = min(axis_values(x, font)[k] for x in font.masters)
				user[a.name] = 0.0 if vals[k] == lo else 1.0
			else:
				user[a.name] = vals[k]
		res[m.id] = user
	return res


def generate_instances(font, planned, replace=True, axis_location=True,
		axis_mappings=False, style_linking=True, elidable=True, variable_setting=True):
	created, skipped = [], []
	existing = dict((i.name, i) for i in font.instances if is_static(i))
	if axis_location:
		mu = _master_user_values(font, planned)
		for m in font.masters:
			set_axis_location(m, font, mu[m.id])
	for p in planned:
		old = existing.get(p["name"])
		if old is not None:
			if not replace:
				skipped.append(p["name"])
				continue
			font.instances.remove(old)
		inst = GSInstance()
		inst.name = p["name"]
		font.instances.append(inst)
		set_axis_values(inst, font, p["design"])
		try:
			inst.weightClass = int(p["weightClass"])
			inst.widthClass = int(p["widthClass"])
		except Exception:
			pass
		if style_linking:
			inst.isItalic = bool(p["isItalic"])
			inst.isBold = bool(p["isBold"])
			if p["linkStyle"]:
				inst.linkStyle = p["linkStyle"]
		if axis_location:
			set_axis_location(inst, font, p["user"])
		if elidable:
			wi, di = axis_index(font, "wght"), axis_index(font, "wdth")
			ii, _ = _italic_axis(font)
			names = []
			if wi is not None and p.get("elidable_weight"):
				names.append(font.axes[wi].name)
			if di is not None and p.get("elidable_width"):
				names.append(font.axes[di].name)
			if ii is not None and not p["italic"]:
				names.append(font.axes[ii].name)
			for n in names:
				cp_append(inst, "Elidable STAT Axis Value Name", n)
		created.append(p["name"])
	if axis_mappings:
		mappings = {}
		for k, a in enumerate(font.axes):
			if a.axisTag not in ("wght", "wdth"):
				continue
			pairs = {}
			for p in planned:
				pairs[fmt_num(p["user"][a.name])] = round(p["design"][k], 3)
			if len(pairs) > 1:
				mappings[a.axisTag] = pairs
		if mappings:
			cp_set(font, "Axis Mappings", mappings)
	if variable_setting and len(font.axes):
		add_variable_setting(font)
	return created, skipped


# ---------------------------------------------------------------------------
# Master baru

def _new_master_from(font, src, name):
	nm = copy.copy(src)
	try:
		nm.id = str(uuid.uuid4()).upper()
	except Exception:
		pass
	nm.name = name
	font.masters.append(nm)
	return nm


def _put_layer(glyph, master_id, layer):
	nl = layer.copy()
	try:
		nl.associatedMasterId = master_id
	except Exception:
		pass
	glyph.layers[master_id] = nl
	return glyph.layers[master_id]


def _kern_key(font, key):
	if key.startswith("@"):
		return key
	try:
		g = font.glyphForId_(key)
		if g is not None:
			return g.name
	except Exception:
		pass
	return key


def _kerning_of(font, master_id):
	try:
		k = font.kerning[master_id]
	except Exception:
		k = None
	out = {}
	for left in (k or {}):
		for right, v in k[left].items():
			out[(_kern_key(font, left), _kern_key(font, right))] = float(v)
	return out


def _set_kerning(font, master_id, pairs):
	for (l, r), v in pairs.items():
		font.setKerningForPair(master_id, l, r, v)


def _metrics(m):
	out = {}
	for attr in ("ascender", "capHeight", "xHeight", "descender", "italicAngle"):
		try:
			out[attr] = float(getattr(m, attr))
		except Exception:
			pass
	return out


def _set_metrics(m, values):
	for attr, v in values.items():
		try:
			setattr(m, attr, int(round(v)) if attr != "italicAngle" else v)
		except Exception:
			pass


def master_name_for_italic(name, suffix="Italic"):
	if name.strip() in ("Regular", ""):
		return suffix
	return ("%s %s" % (name, suffix)).strip()


def slant_layer(layer, angle, origin_y, compensate=False):
	"""Miringkan path & anchor; komponen hanya digeser karena glyph dasarnya
	sudah dimiringkan sendiri. Hasil: True bila ada komponen bertransformasi
	(diskalakan/diputar) yang perlu dicek manual."""
	m = slant_transform(angle, origin_y, compensate)
	sx, t = m[0], m[2]
	for p in layer.paths:
		p.applyTransform(m)
	for a in layer.anchors:
		x, y = apply_affine(m, a.position.x, a.position.y)
		a.position = NSPoint(x, y)
	odd = False
	for c in layer.components:
		x, y = c.position.x, c.position.y
		c.position = NSPoint(sx * x + t * y, y)
		try:
			s = c.scale
			if (abs(s[0] - 1) > 1e-6 or abs(s[1] - 1) > 1e-6) or abs(getattr(c, "rotation", 0) or 0) > 1e-6:
				odd = True
		except Exception:
			pass
	if compensate:
		layer.width = layer.width * sx
	return odd


def make_italic_masters(font, masters, angle, origin="xheight", compensate=False,
		axis_mode="ital", suffix="Italic", kerning=True):
	"""Duplikat master lalu miringkan. axis_mode: 'ital' (0->1), 'slnt'
	(0 -> -sudut, sesuai spesifikasi OpenType), atau 'none'."""
	idx = None
	if axis_mode == "ital":
		idx = axis_index(font, "ital")
		if idx is None:
			add_axis(font, "Italic", "ital", 0)
			idx = len(font.axes) - 1
	elif axis_mode == "slnt":
		idx = axis_index(font, "slnt")
		if idx is None:
			add_axis(font, "Slant", "slnt", 0)
			idx = len(font.axes) - 1
	new, odd = [], set()
	for m in list(masters):
		nm = _new_master_from(font, m, master_name_for_italic(m.name, suffix))
		vals = axis_values(m, font)
		if idx is not None:
			vals[idx] = 1.0 if axis_mode == "ital" else -float(angle)
		set_axis_values(nm, font, vals)
		_set_metrics(nm, _metrics(m))
		nm.italicAngle = float(angle)
		loc = axis_location_dict(m)
		if loc and idx is not None:
			loc[font.axes[idx].name] = vals[idx]
			set_axis_location(nm, font, loc)
		try:
			oy = float(m.xHeight) / 2.0 if origin == "xheight" else 0.0
		except Exception:
			oy = 0.0
		for g in font.glyphs:
			src = g.layers[m.id]
			if src is None:
				continue
			nl = _put_layer(g, nm.id, src)
			if slant_layer(nl, angle, oy, compensate):
				odd.add(g.name)
		if kerning:
			_set_kerning(font, nm.id, _kerning_of(font, m.id))
		new.append(nm)
	return new, sorted(odd)


def _lerp(a, b, t):
	return a + (b - a) * t


def make_master_between(font, ma, mb, axis_idx, value, name, kerning=True):
	"""Master baru di `value` pada axis `axis_idx`, diinterpolasi (atau
	diekstrapolasi bila di luar rentang) dari master A dan B. Contoh: Regular
	+ Bold -> Thin atau Black."""
	va = axis_values(ma, font)
	vb = axis_values(mb, font)
	if va[axis_idx] == vb[axis_idx]:
		raise ValueError("Master A dan B punya nilai sama di axis ini")
	t = (float(value) - va[axis_idx]) / (vb[axis_idx] - va[axis_idx])
	nm = _new_master_from(font, ma, name)
	vals = [_lerp(x, y, t) for x, y in zip(va, vb)]
	vals[axis_idx] = float(value)
	set_axis_values(nm, font, vals)
	ma_m, mb_m = _metrics(ma), _metrics(mb)
	_set_metrics(nm, dict((k, _lerp(ma_m[k], mb_m[k], t)) for k in ma_m if k in mb_m))
	try:
		nm.stems = [_lerp(float(a), float(b), t) for a, b in zip(list(ma.stems), list(mb.stems))]
	except Exception:
		pass
	la, lb = axis_location_dict(ma), axis_location_dict(mb)
	if la and lb:
		set_axis_location(nm, font, dict((k, round(_lerp(la[k], lb[k], t), 3)) for k in la if k in lb))
	bad = []
	for g in font.glyphs:
		a, b = g.layers[ma.id], g.layers[mb.id]
		if a is None:
			continue
		nl = _put_layer(g, nm.id, a)
		if b is None or layer_signature(a) != layer_signature(b):
			bad.append(g.name)
			continue
		for pa, pb, pn in zip(a.paths, b.paths, nl.paths):
			for na, nb, nn in zip(pa.nodes, pb.nodes, pn.nodes):
				(xa, ya), (xb, yb) = _xy(na), _xy(nb)
				nn.position = NSPoint(_lerp(xa, xb, t), _lerp(ya, yb, t))
		bmap = dict((x.name, x) for x in b.anchors)
		for an in nl.anchors:
			ab = bmap.get(an.name)
			if ab is not None:
				x, y = an.position.x, an.position.y
				an.position = NSPoint(_lerp(x, ab.position.x, t), _lerp(y, ab.position.y, t))
		for ca, cb, cn in zip(a.components, b.components, nl.components):
			cn.position = NSPoint(_lerp(ca.position.x, cb.position.x, t), _lerp(ca.position.y, cb.position.y, t))
		nl.width = _lerp(float(a.width), float(b.width), t)
	if kerning:
		ka, kb = _kerning_of(font, ma.id), _kerning_of(font, mb.id)
		pairs = {}
		for key in set(ka) | set(kb):
			pairs[key] = round(_lerp(ka.get(key, 0.0), kb.get(key, 0.0), t))
		_set_kerning(font, nm.id, pairs)
	return nm, bad


def instance_to_master(font, instance, kerning=True):
	"""Jadikan instance sebagai master baru (pakai interpolasi Glyphs)."""
	f2 = instance.interpolatedFont
	m2 = f2.masters[0]
	nm = _new_master_from(font, origin_master(font), instance.name)
	set_axis_values(nm, font, axis_values(instance, font))
	_set_metrics(nm, _metrics(m2))
	loc = axis_location_dict(instance)
	if loc:
		set_axis_location(nm, font, loc)
	missing = []
	for g in font.glyphs:
		g2 = f2.glyphs[g.name]
		layer = g2.layers[m2.id] if g2 is not None else None
		if layer is None:
			missing.append(g.name)
			continue
		_put_layer(g, nm.id, layer)
	if kerning:
		_set_kerning(font, nm.id, _kerning_of(f2, m2.id))
	return nm, missing
# ======================================================================
# vs_window.py
# Variable Studio - jendela utama (Window > Variable Studio).
# Tab: Titik, Axis & Master, Style, Italic, Master Baru, Pratinjau.

import math
import time
import traceback

import objc
from GlyphsApp import Glyphs, Message, UPDATEINTERFACE, WINDOW_MENU
from GlyphsApp.plugins import GeneralPlugin
from AppKit import NSMenuItem, NSPasteboard, NSTimer
from vanilla import (Window, Tabs, TextBox, Button, CheckBox, List, PopUpButton,
	EditText, Slider, TextEditor, HorizontalLine)


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

		self.w = Window((660, 640), "Variable Studio %s" % VS_VERSION, minSize=(600, 560))
		self.w.tabs = Tabs((10, 10, -10, -10), ["Titik", "Axis & Master", "Style", "Italic", "Master Baru", "Pratinjau"])
		self._build_points(self.w.tabs[0])
		self._build_axes(self.w.tabs[1])
		self._build_styles(self.w.tabs[2])
		self._build_italic(self.w.tabs[3])
		self._build_newmaster(self.w.tabs[4])
		self._build_preview(self.w.tabs[5])
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
		t.brace = CheckBox((10, 82, -10, 20), "Ikutkan brace layer {…}", value=pref_bool("showBrace", True), callback=self.on_pref)
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
		t.note = TextBox((10, 250, -10, 80),
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
