# encoding: utf-8
# Variable Studio - inti tanpa Glyphs: model interpolasi, pembaca titik,
# pemeriksa kompatibilitas, perencana style. Semua fungsi di sini bisa dites
# tanpa Glyphs (duck typing pada objek layer/path/node).

from __future__ import division, print_function, unicode_literals
import math

VS_VERSION = "1.3.1"
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


# ---------------------------------------------------------------------------
# Tebal / tipis yang tetap kompatibel (jumlah & urutan titik tidak berubah)

def _unit(vx, vy):
	l = math.hypot(vx, vy)
	return (vx / l, vy / l) if l > 1e-9 else None


def _neighbor_dir(pts, i, step, closed):
	"""Arah dari pts[i] ke tetangga pertama yang tidak berimpit."""
	n = len(pts)
	for k in range(1, n):
		j = i + step * k
		if closed:
			j %= n
		elif j < 0 or j >= n:
			return None
		u = _unit(pts[j][0] - pts[i][0], pts[j][1] - pts[i][1])
		if u:
			return u
	return None


def point_in_polygon(pt, poly):
	x, y = pt
	inside = False
	n = len(poly)
	for i in range(n):
		x1, y1 = poly[i]
		x2, y2 = poly[(i + 1) % n]
		if (y1 > y) != (y2 > y):
			xc = x1 + (y - y1) * (x2 - x1) / (y2 - y1)
			if xc > x:
				inside = not inside
	return inside


def _corner_vector(pts, i, closed, clamp):
	"""Vektor geser (panjang 1 di sisi lurus) ke kanan arah jalan path;
	di sudut dipakai miter agar kedua sisi bergeser sama jauh."""
	normals = []
	tin = _neighbor_dir(pts, i, -1, closed)
	tout = _neighbor_dir(pts, i, 1, closed)
	if tin:
		normals.append((-tin[1], tin[0]))  # kanan dari (-tin)
	if tout:
		normals.append((tout[1], -tout[0]))
	if not normals:
		return (0.0, 0.0)
	if len(normals) == 1:
		return normals[0]
	(ax, ay), (bx, by) = normals
	dot = ax * bx + ay * by
	if dot < -0.999:
		return (ax, ay)
	mx, my = (ax + bx) / (1 + dot), (ay + by) / (1 + dot)
	l = math.hypot(mx, my)
	if l > clamp:
		mx, my = mx * clamp / l, my * clamp / l
	return (mx, my)


def _fit_cubic_offset(ctrl, na, nb, s):
	"""Handle baru untuk kurva kubik yang ujungnya sudah digeser ke na, nb.
	Arah handle tetap (titik halus tetap halus); panjangnya dihitung supaya
	titik tengah kurva (t = 0,5) jatuh tepat di posisi offset-nya. Besar
	offset di tengah diambil dari geseran nyata kedua ujung per sumbu
	(termasuk bobot kontras dan titik zona yang dipegang), jadi bagian
	diagonal lengkung ikut menebal sebanding dengan bagian tegak & datar.
	None bila tidak bisa dipecahkan dengan wajar (pakai skala biasa)."""
	p0, p1, p2, p3 = ctrl
	u0 = _unit(p1[0] - p0[0], p1[1] - p0[1])
	u3 = _unit(p2[0] - p3[0], p2[1] - p3[1])
	if u0 is None or u3 is None:
		return None
	t0 = _bez_tangent(ctrl, 0.0)
	t3 = _bez_tangent(ctrl, 1.0)
	tm = _bez_tangent(ctrl, 0.5)
	if t0 is None or t3 is None or tm is None:
		return None
	# normal ke luar tinta di kedua ujung dan di tengah
	n0 = (t0[1] * s, -t0[0] * s)
	n3 = (t3[1] * s, -t3[0] * s)
	nm = (tm[1] * s, -tm[0] * s)
	d0 = (na[0] - p0[0], na[1] - p0[1])
	d3 = (nb[0] - p3[0], nb[1] - p3[1])
	# besar offset per sumbu: geseran ujung searah normalnya (tumbuh = +),
	# dibobot seberapa besar normal ujung itu mengarah ke sumbu tsb.
	eff = []
	for k in (0, 1):
		w = abs(n0[k]) + abs(n3[k])
		g0 = d0[k] if n0[k] >= 0 else -d0[k]
		g3 = d3[k] if n3[k] >= 0 else -d3[k]
		if w < 0.2:
			eff.append((g0 + g3) / 2.0)
		else:
			eff.append((g0 * (abs(n0[k]) > 1e-9) + g3 * (abs(n3[k]) > 1e-9)) / w)
	mid = _bez(ctrl, 0.5)
	target = (mid[0] + nm[0] * eff[0], mid[1] + nm[1] * eff[1])
	# B(0.5) = (na + nb)/2 + 3/8 (a*u0 + b*u3)  ->  a*u0 + b*u3 = R
	rx = (target[0] - (na[0] + nb[0]) / 2.0) * 8.0 / 3.0
	ry = (target[1] - (na[1] + nb[1]) / 2.0) * 8.0 / 3.0
	det = u0[0] * u3[1] - u0[1] * u3[0]
	if abs(det) < 0.05:
		return None
	la = (rx * u3[1] - ry * u3[0]) / det
	lb = (u0[0] * ry - u0[1] * rx) / det
	chord = math.hypot(nb[0] - na[0], nb[1] - na[1])
	if la <= 0 or lb <= 0 or la > 1.5 * chord or lb > 1.5 * chord:
		return None
	return (na[0] + u0[0] * la, na[1] + u0[1] * la), (nb[0] + u3[0] * lb, nb[1] + u3[1] * lb)


def _segment_scale(a, b, na, nb):
	"""Skala x dan y sebuah segmen dari perubahan titik ujungnya. Bila segmen
	hampir tegak/mendatar, skala sumbu itu memakai rasio tali busur."""
	ca = math.hypot(b[0] - a[0], b[1] - a[1])
	cb = math.hypot(nb[0] - na[0], nb[1] - na[1])
	kf = cb / ca if ca > 1e-6 else 1.0
	out = []
	for k in (0, 1):
		d0 = b[k] - a[k]
		d1 = nb[k] - na[k]
		if abs(d0) > max(1.0, 0.05 * ca):
			out.append(max(0.1, min(10.0, d1 / d0)))
		else:
			out.append(kf)
	return out[0], out[1]


def path_side(polys, pi):
	"""+1 bila sisi kanan arah jalan path pi = luar tinta, -1 sebaliknya.
	Counter = kontur yang SELURUH titiknya di dalam kontur lain; kontur yang
	hanya bertumpuk (belum Remove Overlap) tetap dianggap kontur luar."""
	pts = polys[pi]
	depth = sum(1 for qi, q in enumerate(polys) if qi != pi and len(q) > 2
		and all(point_in_polygon(pt, q) for pt in pts))
	return 1.0 if (signed_area(pts) > 0) == (depth % 2 == 0) else -1.0


def offset_paths(paths, dx, dy, selected=None, clamp=3.0, weights=None, pins=None):
	"""Geser kontur sejauh dx (arah x) dan dy (arah y) per sisi. Positif =
	lebih tebal: kontur luar melebar, counter menyempit (ditentukan dari
	tumpukan kontur, jadi tetap benar walau arah path belum dirapikan).
	paths: [(closed, types, pts)]. selected: set (indeks path, indeks node)
	untuk hanya menggeser titik terpilih. weights: {(path, node): (wx, wy)}
	pengali geser per titik (0 = titik diam), dari contrast_weights().
	pins: {(path, node): titik seberang atau None} dari zone_pins(): titik
	ini tidak bergeser vertikal, geser vertikalnya dipindahkan ke titik
	seberang goresan (jadi goresan tetap bertambah penuh, tumbuh ke dalam).
	Hasil: list pts baru per path."""
	polys = [list(pts) for _, _, pts in paths]
	vecs = {}
	for pi, (closed, types, pts) in enumerate(paths):
		n = len(pts)
		on = [i for i in range(n) if types[i] != "offcurve"]
		if n < 2 or not closed or not on:
			continue
		s = path_side(polys, pi)
		for i in on:
			if selected is not None and (pi, i) not in selected:
				vecs[(pi, i)] = (0.0, 0.0)
				continue
			mx, my = _corner_vector(pts, i, closed, clamp)
			wx, wy = weights.get((pi, i), (1.0, 1.0)) if weights else (1.0, 1.0)
			vecs[(pi, i)] = (mx * dx * s * wx, my * dy * s * wy)
	if pins:
		extra = {}
		for key, target in pins.items():
			if key not in vecs:
				continue
			vx, vy = vecs[key]
			vecs[key] = (vx, 0.0)
			if target is not None and target in vecs and abs(vy) > abs(extra.get(target, 0.0)):
				extra[target] = -vy
		for key, ey in extra.items():
			vecs[key] = (vecs[key][0], vecs[key][1] + ey)
	out = []
	for pi, (closed, types, pts) in enumerate(paths):
		pts = [(float(x), float(y)) for x, y in pts]
		n = len(pts)
		on = [i for i in range(n) if types[i] != "offcurve"]
		if n < 2 or not closed or not on:
			out.append(pts)
			continue
		vec = dict((i, vecs.get((pi, i), (0.0, 0.0))) for i in on)
		path_s = path_side(polys, pi)
		new = list(pts)
		for i in on:
			new[i] = (pts[i][0] + vec[i][0], pts[i][1] + vec[i][1])
		for k, a in enumerate(on):
			b = on[(k + 1) % len(on)]
			offs = []
			j = (a + 1) % n
			while j != b:
				offs.append(j)
				j = (j + 1) % n
			if not offs:
				continue
			sx, sy = _segment_scale(pts[a], pts[b], new[a], new[b])
			if selected is None and len(offs) == 2:
				fit = _fit_cubic_offset([pts[a], pts[offs[0]], pts[offs[1]], pts[b]], new[a], new[b], path_s)
				if fit is not None:
					new[offs[0]], new[offs[1]] = fit
					continue
			for m, j in enumerate(offs):
				base = a if m < len(offs) / 2.0 else b
				if selected is not None:
					new[j] = (pts[j][0] + vec[base][0], pts[j][1] + vec[base][1])
					continue
				hx, hy = pts[j][0] - pts[base][0], pts[j][1] - pts[base][1]
				u = _unit(hx, hy)
				# arah handle tetap (titik halus tetap halus), panjangnya ikut
				# perubahan segmen pada sumbu searah handle: handle tegak ikut
				# skala-y, handle mendatar ikut skala-x (elips tetap elips)
				f = math.hypot(u[0] * sx, u[1] * sy) if u else 1.0
				new[j] = (new[base][0] + hx * f, new[base][1] + hy * f)
		out.append(new)
	return out


def _bbox(path_pts):
	xs = [p[0] for pts in path_pts for p in pts]
	ys = [p[1] for pts in path_pts for p in pts]
	if not xs:
		return None
	return min(xs), min(ys), max(xs), max(ys)


def zone_pins(paths, zones, tolerance=25.0, measure=None):
	"""Titik ekstrem yang menempel di garis metrik (baseline, x-height, cap,
	ascender, descender; termasuk overshoot) dan tepinya menghadap ke luar
	zona itu: puncak di zona atas, dasar di zona bawah. Hasil:
	{(path, node): titik on-curve di seberang goresan, atau None bila titik
	ini ujung garis (mis. dasar stem: memanjang, bukan menebal)}."""
	if not zones:
		return {}
	polys = [list(p[2]) for p in paths]
	cand = {}
	for pi, (closed, types, pts) in enumerate(paths):
		n = len(pts)
		if not closed or n < 3:
			continue
		s = path_side(polys, pi)
		for i in range(n):
			if types[i] == "offcurve":
				continue
			y = pts[i][1]
			ny = [pts[(i - 1) % n][1], pts[(i + 1) % n][1]]
			mx, my = _corner_vector(pts, i, closed, 3.0)
			out_y = my * s
			for z in zones:
				if abs(y - z) > tolerance:
					continue
				top = z > 0
				if top and out_y > 0.2 and all(v <= y + 0.5 for v in ny):
					cand[(pi, i)] = (z, True, y)
				elif not top and out_y < -0.2 and all(v >= y - 0.5 for v in ny):
					cand[(pi, i)] = (z, False, y)
	if not cand:
		return {}
	m = measure if measure is not None else measure_paths(paths)
	ons = [((pi, i), pts[i]) for pi, (closed, types, pts) in enumerate(paths) if closed
		for i in range(len(pts)) if types[i] != "offcurve"]
	pins = {}
	for key in cand:
		target = None
		sms = [x for x in m.get("near", {}).get(key, []) if not x.get("terminal")]
		if sms:
			sm = max(sms, key=lambda x: abs(x["normal"][1]))
			if abs(sm["normal"][1]) > 0.5:
				ex, ey = sm["end"]
				radius = max(15.0, 0.6 * sm["th"])
				best = None
				for k2, p in ons:
					if k2 == key or k2 in cand:
						continue
					d = math.hypot(p[0] - ex, p[1] - ey)
					if d <= radius and (best is None or d < best[0]):
						best = (d, k2)
				target = best[1] if best else None
		pins[key] = target
	return pins


def zone_limits(paths, pins):
	"""Batas per zona dari titik yang dipegang: [(batas, atas?)]. Titik yang
	semula di bawah batas atas tidak boleh naik melewatinya (dan sebaliknya),
	supaya lengkung di sekitar puncak tidak menonjol keluar zona."""
	groups = {}
	for (pi, i) in pins:
		y = paths[pi][2][i][1]
		# zona atas bila tepinya menghadap ke atas (lihat zone_pins)
		pts = paths[pi][2]
		n = len(pts)
		top = all(pts[(i + d) % n][1] <= y + 0.5 for d in (-1, 1))
		key = (top, round(y / 50.0))
		groups.setdefault(key, []).append(y)
	out = []
	for (top, _), ys in groups.items():
		out.append((max(ys) if top else min(ys), top))
	return out


def embolden(paths, width, add_x, add_y, keep_sidebearings=True, zones=None,
		selected=None, tolerance=25.0, weights=None, measure=None):
	"""Tambah tebal stem tegak sebesar add_x dan stem datar sebesar add_y
	(negatif = lebih tipis). zones: garis metrik (0, x-height, cap height,
	...); titik yang menempel padanya tidak bergeser vertikal dan goresannya
	tumbuh ke dalam (lihat zone_pins). Hasil dict: pts (per path), width,
	anchor(x, y) -> (x, y)."""
	old = [list(p[2]) for p in paths]
	pins = zone_pins(paths, zones, tolerance, measure) if (zones and selected is None) else None
	new = offset_paths(paths, add_x / 2.0, add_y / 2.0, selected, weights=weights, pins=pins)
	if pins:
		for limit, top in zone_limits(paths, pins):
			for pi, pts in enumerate(new):
				for j, (x, y) in enumerate(pts):
					oy = old[pi][j][1]
					if top and oy <= limit + 0.5 and y > limit:
						pts[j] = (x, limit)
					elif not top and oy >= limit - 0.5 and y < limit:
						pts[j] = (x, limit)
	ob, nb = _bbox(old), _bbox(new)
	ident = {"pts": new, "width": width, "anchor": lambda x, y: (x, y)}
	if selected is not None or ob is None or nb is None:
		return ident
	ox0, oy0, ox1, oy1 = ob
	nx0, ny0, nx1, ny1 = nb
	ow, nw = ox1 - ox0, nx1 - nx0
	tx = (ox0 - nx0) if keep_sidebearings else 0.0
	new_width = width + (nw - ow) if keep_sidebearings else width
	pts = [[(x + tx, y) for x, y in p] for p in new]
	x0 = ox0 if keep_sidebearings else nx0

	def anchor(x, y):
		if ow > 1e-6:
			return (x0 + (x - ox0) * nw / ow, y)
		return (x + tx, y)
	return {"pts": pts, "width": new_width, "anchor": anchor}


# ---------------------------------------------------------------------------
# Ukur ketebalan garis (stroke) & bobot kontras

CONTRAST_MODES = [
	("uniform", "Rata: semua garis bertambah sama"),
	("keepthin", "Pertahankan garis tipis, tebalkan yang tebal"),
	("keepthick", "Pertahankan garis tebal, ubah yang tipis"),
]


def _bez(ctrl, t):
	pts = list(ctrl)
	while len(pts) > 1:
		pts = [(a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t) for a, b in zip(pts, pts[1:])]
	return pts[0]


def _bez_tangent(ctrl, t):
	h = 1e-4
	a = _bez(ctrl, max(0.0, t - h))
	b = _bez(ctrl, min(1.0, t + h))
	u = _unit(b[0] - a[0], b[1] - a[1])
	if u is None:
		u = _unit(ctrl[-1][0] - ctrl[0][0], ctrl[-1][1] - ctrl[0][1])
	return u


def path_curves(paths):
	"""[(indeks path, node awal, node akhir, titik kontrol)] untuk tiap segmen
	path tertutup."""
	out = []
	for pi, (closed, types, pts) in enumerate(paths):
		n = len(pts)
		on = [i for i in range(n) if types[i] != "offcurve"]
		if not closed or len(on) < 2:
			continue
		for k, a in enumerate(on):
			b = on[(k + 1) % len(on)]
			ctrl = [pts[a]]
			j = (a + 1) % n
			while j != b:
				ctrl.append(pts[j])
				j = (j + 1) % n
			ctrl.append(pts[b])
			out.append((pi, a, b, ctrl))
	return out


def _ray_hit(p, d, lines, eps=1.0):
	best = None
	px, py = p
	dx, dy = d
	for x1, y1, x2, y2 in lines:
		ex, ey = x2 - x1, y2 - y1
		den = dx * ey - dy * ex
		if abs(den) < 1e-12:
			continue
		t = ((x1 - px) * ey - (y1 - py) * ex) / den
		if t <= eps or (best is not None and t >= best):
			continue
		u = ((x1 - px) * dy - (y1 - py) * dx) / den
		if -1e-9 <= u <= 1 + 1e-9:
			best = t
	return best


def _dist_to_lines(p, lines):
	px, py = p
	best = None
	for x1, y1, x2, y2 in lines:
		ex, ey = x2 - x1, y2 - y1
		ll = ex * ex + ey * ey
		u = 0.0 if ll < 1e-12 else max(0.0, min(1.0, ((px - x1) * ex + (py - y1) * ey) / ll))
		d = math.hypot(px - (x1 + ex * u), py - (y1 + ey * u))
		if best is None or d < best:
			best = d
	return best


def _contour_roles(polys):
	"""+1 untuk kontur luar, -1 untuk counter (dari tumpukan, bukan arah)."""
	roles = []
	for pi, pts in enumerate(polys):
		depth = sum(1 for qi, q in enumerate(polys) if qi != pi and len(q) > 2 and len(pts) > 2
			and all(point_in_polygon(pt, q) for pt in pts))
		roles.append(1 if depth % 2 == 0 else -1)
	return roles


def _has_overlap(polys, roles):
	boxes = []
	for pts in polys:
		xs = [p[0] for p in pts]
		ys = [p[1] for p in pts]
		boxes.append((min(xs), min(ys), max(xs), max(ys)) if pts else None)
	for i in range(len(polys)):
		for j in range(i + 1, len(polys)):
			a, b = boxes[i], boxes[j]
			if a is None or b is None or a[2] < b[0] or b[2] < a[0] or a[3] < b[1] or b[3] < a[1]:
				continue
			inside = (all(point_in_polygon(p, polys[j]) for p in polys[i])
				or all(point_in_polygon(p, polys[i]) for p in polys[j]))
			if not inside:
				return True
	return False


def measure_paths(paths, max_piece=20.0, max_thickness=3000.0):
	"""Ukur ketebalan garis. Dari titik di tepi, sinar ditembakkan tegak lurus
	ke dalam tinta sampai keluar; ketebalan = diameter lingkaran terbesar di
	tengah sinar itu, diambil yang lebih kecil dari panjang sinar dan lebar
	melintang di tengahnya (jadi ujung stem terukur selebar stem, bukan
	sepanjang stem). Kontur yang bertumpuk (belum Remove Overlap) diperhitungkan: hanya
	tepi luar gabungan tinta yang dipakai. Hasil dict:
	  samples: [{pt, end, normal, th, dir}]  dir 'v' = garis tegak (tebal
	           diukur mendatar), 'h' = garis datar
	  nodes:   {(path, node): (tebal untuk geser-x, tebal untuk geser-y)}"""
	closed_idx = [pi for pi, p in enumerate(paths) if p[0] and len(p[2]) > 2]
	curves = path_curves(paths)
	# kontur dipecah jadi potongan garis pendek
	pieces = dict((pi, []) for pi in closed_idx)
	for pi, a, b, ctrl in curves:
		if pi not in pieces:
			continue
		length = sum(math.hypot(q[0] - p[0], q[1] - p[1]) for p, q in zip(ctrl, ctrl[1:]))
		k = max(1 if len(ctrl) == 2 else 8, int(math.ceil(length / max_piece)))
		prev = ctrl[0]
		for j in range(1, k + 1):
			q = _bez(ctrl, j / float(k))
			pieces[pi].append((prev[0], prev[1], q[0], q[1]))
			prev = q
	polys = [[(l[0], l[1]) for l in pieces[pi]] for pi in closed_idx]
	roles = _contour_roles(polys)
	role_of = dict(zip(closed_idx, roles))
	side_of = dict((pi, path_side([list(paths[q][2]) for q in closed_idx], k))
		for k, pi in enumerate(closed_idx))
	overlap = _has_overlap(polys, roles)

	def ink(pt):
		return sum(r for r, poly in zip(roles, polys) if point_in_polygon(pt, poly)) > 0

	lines = []
	for pi in closed_idx:
		for l in pieces[pi]:
			if overlap:
				u = _unit(l[2] - l[0], l[3] - l[1])
				if u is None:
					continue
				s = side_of[pi]
				mx, my = (l[0] + l[2]) / 2.0, (l[1] + l[3]) / 2.0
				# tepi gabungan = di satu sisi tinta, di sisi lain kosong
				out_pt = (mx + u[1] * s * 0.5, my - u[0] * s * 0.5)
				in_pt = (mx - u[1] * s * 0.5, my + u[0] * s * 0.5)
				if ink(out_pt) or not ink(in_pt):
					continue
			lines.append(l)

	def sample(pi, ctrl, t):
		tan = _bez_tangent(ctrl, t)
		if tan is None or pi not in side_of:
			return None
		s = side_of[pi]
		inward = (-tan[1] * s, tan[0] * s)
		p = _bez(ctrl, t)
		if overlap and (ink((p[0] - inward[0], p[1] - inward[1])) or not ink((p[0] + inward[0], p[1] + inward[1]))):
			return None  # titik ini tertutup kontur lain, bukan tepi tinta
		hit = _ray_hit(p, inward, lines)
		if hit is None or hit > max_thickness:
			return None
		# lebar melintang di sepanjang sinar: di ujung stem sinar berjalan
		# sepanjang stem, tapi lebar melintangnya tetap = tebal stem
		th = hit
		for f in (0.25, 0.5, 0.75):
			q = (p[0] + inward[0] * hit * f, p[1] + inward[1] * hit * f)
			a1 = _ray_hit(q, (-inward[1], inward[0]), lines, eps=0.0)
			a2 = _ray_hit(q, (inward[1], -inward[0]), lines, eps=0.0)
			if a1 is not None and a2 is not None:
				th = min(th, a1 + a2)
		return {"pt": p, "end": (p[0] + inward[0] * hit, p[1] + inward[1] * hit), "normal": inward,
			"th": th, "dir": "v" if abs(inward[0]) >= abs(inward[1]) else "h",
			# ujung garis: sinar berjalan sepanjang garis, jauh lebih panjang dari tebalnya
			"terminal": hit > 2.5 * th}

	samples = []
	near, mids = {}, {}
	for pi, a, b, ctrl in curves:
		for t in (0.04, 0.5, 0.96):
			sm = sample(pi, ctrl, t)
			if sm is None:
				continue
			samples.append(sm)
			if t == 0.04:
				near.setdefault((pi, a), []).append(sm)
			elif t == 0.96:
				near.setdefault((pi, b), []).append(sm)
			else:
				mids.setdefault((pi, a), []).append(sm)
				mids.setdefault((pi, b), []).append(sm)
	nodes, hold = {}, {}
	for key in set(near) | set(mids):
		# titik yang tertutup kontur lain memakai ukuran tengah segmennya
		sms = (near.get(key, []) if not overlap or len(near.get(key, [])) == 2 else []) or mids.get(key, [])
		if not sms:
			continue
		sx = max(sms, key=lambda m: abs(m["normal"][0]))
		sy = max(sms, key=lambda m: abs(m["normal"][1]))
		nodes[key] = (sx["th"], sy["th"])
		if overlap:
			# ujung garis yang tersembunyi di dalam kontur lain (mis. ujung palang
			# H di dalam stem) tidak boleh bergeser memanjang, nanti menembus keluar
			pi, ni = key
			px, py = paths[pi][2][ni]
			nx = sum(m["normal"][0] for m in sms)
			ny = sum(m["normal"][1] for m in sms)
			u = _unit(nx, ny)
			if u is not None:
				q = (px + u[0], py + u[1])
				hidden = any(point_in_polygon(q, poly) for k, poly in zip(closed_idx, polys) if k != pi)
				if hidden:
					hold[key] = (sx.get("terminal", False), sy.get("terminal", False))
	return {"samples": samples, "nodes": nodes, "hold": hold, "near": near}


def thickness_summary(measure):
	"""Tertipis/tertebal (keseluruhan, garis tegak, garis datar)."""
	out = {}
	for key, flt in (("all", None), ("v", "v"), ("h", "h")):
		ths = [m["th"] for m in measure["samples"] if flt is None or m["dir"] == flt]
		out[key] = (min(ths), max(ths)) if ths else (None, None)
	return out


def _median(vals):
	vals = sorted(vals)
	if not vals:
		return None
	n = len(vals)
	return vals[n // 2] if n % 2 else (vals[n // 2 - 1] + vals[n // 2]) / 2.0


def auto_thresholds(per_glyph):
	"""per_glyph: [(tertipis, tertebal)] -> (batas tipis, batas tebal).
	Hairline tipikal = median tertipis, stem tipikal = median tertebal; garis
	sampai 1,2x hairline tidak diubah, mulai 0,8x stem ditebalkan penuh."""
	thin = _median([a for a, b in per_glyph if a is not None])
	thick = _median([b for a, b in per_glyph if b is not None])
	if thin is None or thick is None:
		return None, None
	lo = thin * 1.2
	hi = max(thick * 0.8, lo + 1.0)
	return round(lo, 1), round(hi, 1)


def contrast_factor(th, lo, hi, mode):
	"""0..1: seberapa besar garis setebal th ikut ditebalkan."""
	if mode == "uniform" or th is None:
		return 1.0
	if hi <= lo:
		f = 1.0 if th >= hi else 0.0
	else:
		t = max(0.0, min(1.0, (th - lo) / float(hi - lo)))
		f = t * t * (3 - 2 * t)  # smoothstep: peralihan halus
	return 1.0 - f if mode == "keepthick" else f


def contrast_weights(measure, lo, hi, mode):
	if mode == "uniform":
		return None
	out = {}
	hold = measure.get("hold", {})
	for key, (tx, ty) in measure["nodes"].items():
		hx, hy = hold.get(key, (False, False))
		out[key] = (0.0 if hx else contrast_factor(tx, lo, hi, mode), 0.0 if hy else contrast_factor(ty, lo, hi, mode))
	return out


# ---------------------------------------------------------------------------
# Garis ukur tetap (lebar goresan di ketinggian / posisi tertentu)

def flatten_contours(paths, max_piece=20.0):
	"""Poligon per kontur tertutup + perannya (+1 luar, -1 counter)."""
	pieces = {}
	for pi, a, b, ctrl in path_curves(paths):
		length = sum(math.hypot(q[0] - p[0], q[1] - p[1]) for p, q in zip(ctrl, ctrl[1:]))
		k = 1 if len(ctrl) == 2 else max(8, int(math.ceil(length / max_piece)))
		poly = pieces.setdefault(pi, [])
		for j in range(0, k):
			poly.append(_bez(ctrl, j / float(k)))
	polys = [pieces[pi] for pi in sorted(pieces)]
	return polys, _contour_roles(polys)


def scanline_runs(polys, roles, value, vertical=False):
	"""Potong tinta dengan garis y = value (mendatar) atau x = value (tegak).
	Hasil: [(awal, akhir, tinta?)] berurutan, dari tepi tinta pertama sampai
	terakhir; tinta=False = celah putih (counter) di antaranya."""
	cuts = []
	ax, bx = (0, 1) if vertical else (1, 0)  # ax: koordinat yang dipotong, bx: sepanjang garis
	for poly in polys:
		n = len(poly)
		for i in range(n):
			p, q = poly[i], poly[(i + 1) % n]
			if (p[ax] > value) != (q[ax] > value):
				t = (value - p[ax]) / (q[ax] - p[ax])
				cuts.append(p[bx] + (q[bx] - p[bx]) * t)
	cuts = sorted(set(round(c, 6) for c in cuts))
	if len(cuts) < 2:
		return []

	def ink(c):
		pt = (value, c) if vertical else (c, value)
		return sum(r for r, poly in zip(roles, polys) if point_in_polygon(pt, poly)) > 0

	runs = []
	for a, b in zip(cuts, cuts[1:]):
		if b - a < 1e-6:
			continue
		on = ink((a + b) / 2.0)
		if runs and runs[-1][2] == on:
			runs[-1] = (runs[-1][0], b, on)  # gabung potongan kontur bertumpuk
		else:
			runs.append((a, b, on))
	while runs and not runs[0][2]:
		runs.pop(0)
	while runs and not runs[-1][2]:
		runs.pop()
	return runs


def parse_heights(text, metrics):
	"""'x/2, H/2, 300' -> [250.0, 350.0, 300.0]. Kata: x (x-height),
	H (cap height), a (ascender), d (descender), boleh /n atau *f."""
	out = []
	for tok in (text or "").replace(";", ",").split(","):
		tok = tok.strip()
		if not tok:
			continue
		base, op, num = tok, None, None
		for sym in ("/", "*"):
			if sym in tok:
				base, num = tok.split(sym, 1)
				op = sym
				break
		base = base.strip()
		if base in metrics:
			v = float(metrics[base])
		else:
			v = float(base.replace(",", "."))
		if op:
			f = float(num.strip())
			v = v / f if op == "/" else v * f
		out.append(v)
	return out


def label_positions(samples, min_gap, occupied=()):
	"""Pilih sampel ukur yang labelnya tidak bertumpuk (jarak >= min_gap),
	termasuk terhadap posisi label lain yang sudah dipakai (occupied)."""
	chosen = []
	taken = [tuple(p) for p in occupied]
	for sm in sorted(samples, key=lambda s: s["th"]):
		if sm.get("terminal"):
			continue  # ujung garis: sinarnya sepanjang goresan, sisi goresan sudah terlabeli
		mx = (sm["pt"][0] + sm["end"][0]) / 2.0
		my = (sm["pt"][1] + sm["end"][1]) / 2.0
		if all(math.hypot(mx - c[0], my - c[1]) >= min_gap for c in taken):
			chosen.append((mx, my, sm))
			taken.append((mx, my))
	return chosen
