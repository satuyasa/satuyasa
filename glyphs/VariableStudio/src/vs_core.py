# encoding: utf-8
# Variable Studio - inti tanpa Glyphs: model interpolasi, pembaca titik,
# pemeriksa kompatibilitas, perencana style. Semua fungsi di sini bisa dites
# tanpa Glyphs (duck typing pada objek layer/path/node).

from __future__ import division, print_function, unicode_literals
import math

VS_VERSION = "1.1.0"
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


def offset_paths(paths, dx, dy, selected=None, clamp=3.0):
	"""Geser kontur sejauh dx (arah x) dan dy (arah y) per sisi. Positif =
	lebih tebal: kontur luar melebar, counter menyempit (ditentukan dari
	tumpukan kontur, jadi tetap benar walau arah path belum dirapikan).
	paths: [(closed, types, pts)]. selected: set (indeks path, indeks node)
	untuk hanya menggeser titik terpilih. Hasil: list pts baru per path."""
	polys = [list(pts) for _, _, pts in paths]
	out = []
	for pi, (closed, types, pts) in enumerate(paths):
		pts = [(float(x), float(y)) for x, y in pts]
		n = len(pts)
		on = [i for i in range(n) if types[i] != "offcurve"]
		if n < 2 or not closed or not on:
			out.append(pts)
			continue
		# counter = kontur yang SELURUH titiknya di dalam kontur lain; kontur yang
		# hanya bertumpuk (belum Remove Overlap) tetap dianggap kontur luar
		depth = sum(1 for qi, q in enumerate(polys) if qi != pi and len(q) > 2
			and all(point_in_polygon(pt, q) for pt in pts))
		s = 1.0 if (signed_area(pts) > 0) == (depth % 2 == 0) else -1.0
		vec = {}
		for i in on:
			if selected is not None and (pi, i) not in selected:
				vec[i] = (0.0, 0.0)
				continue
			mx, my = _corner_vector(pts, i, closed, clamp)
			vec[i] = (mx * dx * s, my * dy * s)
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
			ca = math.hypot(pts[b][0] - pts[a][0], pts[b][1] - pts[a][1])
			cb = math.hypot(new[b][0] - new[a][0], new[b][1] - new[a][1])
			kf = cb / ca if ca > 1e-6 else 1.0
			for m, j in enumerate(offs):
				base = a if m < len(offs) / 2.0 else b
				if selected is not None:
					new[j] = (pts[j][0] + vec[base][0], pts[j][1] + vec[base][1])
				else:
					new[j] = (new[base][0] + (pts[j][0] - pts[base][0]) * kf,
						new[base][1] + (pts[j][1] - pts[base][1]) * kf)
		out.append(new)
	return out


def _bbox(path_pts):
	xs = [p[0] for pts in path_pts for p in pts]
	ys = [p[1] for pts in path_pts for p in pts]
	if not xs:
		return None
	return min(xs), min(ys), max(xs), max(ys)


def embolden(paths, width, add_x, add_y, keep_sidebearings=True, zones=None,
		selected=None, tolerance=25.0):
	"""Tambah tebal stem tegak sebesar add_x dan stem datar sebesar add_y
	(negatif = lebih tipis). zones: garis metrik (0, x-height, cap height,
	...) yang tetap dipegang bila bagian atas/bawah glyph menempel padanya.
	Hasil dict: pts (per path), width, anchor(x, y) -> (x, y)."""
	old = [list(p[2]) for p in paths]
	new = offset_paths(paths, add_x / 2.0, add_y / 2.0, selected)
	ob, nb = _bbox(old), _bbox(new)
	ident = {"pts": new, "width": width, "anchor": lambda x, y: (x, y)}
	if selected is not None or ob is None or nb is None:
		return ident
	ox0, oy0, ox1, oy1 = ob
	nx0, ny0, nx1, ny1 = nb
	ow, nw = ox1 - ox0, nx1 - nx0
	tx = (ox0 - nx0) if keep_sidebearings else 0.0
	new_width = width + (nw - ow) if keep_sidebearings else width

	ymap = lambda y: y
	if zones:
		bottom = any(abs(oy0 - z) <= tolerance for z in zones)
		top = any(abs(oy1 - z) <= tolerance for z in zones)
		oh, nh = oy1 - oy0, ny1 - ny0
		if bottom and top and nh > 1e-6:
			ymap = lambda y: oy0 + (y - ny0) * oh / nh
		elif bottom:
			ymap = lambda y: y + (oy0 - ny0)
		elif top:
			ymap = lambda y: y + (oy1 - ny1)
	pts = [[(x + tx, ymap(y)) for x, y in p] for p in new]
	x0 = ox0 if keep_sidebearings else nx0

	def anchor(x, y):
		if ow > 1e-6:
			return (x0 + (x - ox0) * nw / ow, y)
		return (x + tx, y)
	return {"pts": pts, "width": new_width, "anchor": anchor}
