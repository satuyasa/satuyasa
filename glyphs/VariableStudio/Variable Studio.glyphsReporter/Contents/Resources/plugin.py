# encoding: utf-8
# DIBUAT OLEH src/build.py - jangan edit di sini, edit src/*.py

# ======================================================================
# vs_core.py
# Variable Studio - inti tanpa Glyphs: model interpolasi, pembaca titik,
# pemeriksa kompatibilitas, perencana style. Semua fungsi di sini bisa dites
# tanpa Glyphs (duck typing pada objek layer/path/node).

import math

VS_VERSION = "1.7.1"
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


def terminal_caps(paths, max_len=40.0, taper_deg=12.0):
	"""Ujung runcing: segmen pendek (<= max_len) di antara dua titik sudut
	yang kedua sisinya menyempit ke arah segmen itu (sudut antar sisi >
	taper_deg). Ujung slab serif (sisi sejajar) dan dasar stem (panjang)
	tidak termasuk. Hasil: [(path, a, b, sumbu goresan ke arah ujung)]."""
	polys = [list(p[2]) for p in paths]
	out = []
	cos_t = math.cos(math.radians(taper_deg))
	for pi, (closed, types, pts) in enumerate(paths):
		n = len(pts)
		on = [k for k in range(n) if types[k] != "offcurve"]
		if not closed or len(on) < 3:
			continue
		side = path_side(polys, pi)
		for j, a in enumerate(on):
			b = on[(j + 1) % len(on)]
			if math.hypot(pts[b][0] - pts[a][0], pts[b][1] - pts[a][1]) > max_len:
				continue
			corner = True
			for k in (a, b):
				tin = _neighbor_dir(pts, k, -1, True)
				tout = _neighbor_dir(pts, k, 1, True)
				if not tin or not tout or tin[0] * tout[0] + tin[1] * tout[1] < -0.97:
					corner = False
			if not corner:
				continue
			# arah sisi menjauh dari ujung
			sa = _neighbor_dir(pts, a, -1, True)
			sb = _neighbor_dir(pts, b, 1, True)
			if sa[0] * sb[0] + sa[1] * sb[1] > cos_t:
				continue  # sisi sejajar: bukan ujung runcing
			# sumbu goresan menuju ujung: kebalikan rata-rata arah kedua sisi
			axis = _unit(-(sa[0] + sb[0]), -(sa[1] + sb[1]))
			if axis is None:
				continue
			out.append((pi, a, b, axis))
	return out


def stabilize_terminals(paths, vecs, pins, max_len=40.0):
	"""Ujung runcing digeser utuh: panjang & sudutnya tetap (tidak tumpul).
	Searah sumbu goresan diambil geseran terjauh (ekor tidak memendek),
	melintang dirata-rata; titik zona yang dipegang tetap di zonanya."""
	for pi, a, b, nrm in terminal_caps(paths, max_len):
		ka, kb = (pi, a), (pi, b)
		if ka not in vecs or kb not in vecs:
			continue
		va, vb = vecs[ka], vecs[kb]
		tng = (-nrm[1], nrm[0])
		along = max(va[0] * nrm[0] + va[1] * nrm[1], vb[0] * nrm[0] + vb[1] * nrm[1])
		across = ((va[0] + vb[0]) * tng[0] + (va[1] + vb[1]) * tng[1]) / 2.0
		v = (nrm[0] * along + tng[0] * across, nrm[1] * along + tng[1] * across)
		if ka in pins or kb in pins:
			# ujung menempel di zona: tidak bergeser vertikal
			v = (v[0], 0.0)
		vecs[ka] = v
		vecs[kb] = v


def _apply_axis_bias(vecs, outs, bx, by, pins):
	"""Arah kiri/kanan & bawah/atas: sisi goresan yang menghadap kiri
	mendapat (1 - bx) bagian geseran-x, yang menghadap kanan (1 + bx)
	(sama untuk bawah/atas dengan by). Tiap goresan punya sepasang sisi
	berlawanan, jadi total tebal tetap. Goresan yang menempel zona
	(titik dipegang & titik seberangnya) tidak dibagi ulang vertikal,
	supaya tambahan tebalnya tidak hilang."""
	targets = set(t for t in pins.values() if t is not None)
	for key, (vx, vy) in list(vecs.items()):
		o = outs.get(key)
		if o is None:
			continue
		fx = fy = 1.0
		if bx:
			if o[0] > 0.05:
				fx = 1.0 + bx
			elif o[0] < -0.05:
				fx = 1.0 - bx
		if by and key not in pins and key not in targets:
			if o[1] > 0.05:
				fy = 1.0 + by
			elif o[1] < -0.05:
				fy = 1.0 - by
		vecs[key] = (vx * fx, vy * fy)


def _apply_io_bias_selected(paths, vecs, outs, bio):
	"""Dalam/luar untuk titik terpilih: sisi yang menghadap ruang putih
	tertutup (counter) x (1 - bio), sisi yang menghadap luar x (1 + bio)."""
	lines = _flat_lines(paths)
	for key, (vx, vy) in list(vecs.items()):
		o = outs.get(key)
		u = _unit(*o) if o else None
		if u is None or (vx == 0 and vy == 0):
			continue
		p = paths[key[0]][2][key[1]]
		inside = _ray_hit((p[0] + u[0] * 0.5, p[1] + u[1] * 0.5), u, lines, eps=0.5) is not None
		f = (1.0 - bio) if inside else (1.0 + bio)
		vecs[key] = (vx * f, vy * f)


def _redistribute_tangential(paths, vecs):
	"""Titik halus yang akan keluar dari rentang kedua tetangganya digeser
	sepanjang garis singgungnya, sebanding posisinya di antara mereka. Saat counter menyempit
	(mis. counter n di bobot Black), puncaknya ikut ke tengah dan lengkung
	tidak terlipat; pada bentuk simetris (o) hasilnya tidak berubah."""
	base = dict(vecs)
	for pi, (closed, types, pts) in enumerate(paths):
		n = len(pts)
		on = [k for k in range(n) if types[k] != "offcurve"]
		if not closed or len(on) < 3:
			continue
		for j, i in enumerate(on):
			if (pi, i) not in base:
				continue
			tin = _neighbor_dir(pts, i, -1, True)
			tout = _neighbor_dir(pts, i, 1, True)
			if not tin or not tout or tin[0] * tout[0] + tin[1] * tout[1] > -0.99:
				continue  # bukan titik halus
			a, b = on[j - 1], on[(j + 1) % len(on)]
			if (pi, a) not in base or (pi, b) not in base:
				continue
			A, B, P = pts[a], pts[b], pts[i]
			ab = (B[0] - A[0], B[1] - A[1])
			ll = ab[0] * ab[0] + ab[1] * ab[1]
			if ll < 1e-9:
				continue
			rel = max(0.0, min(1.0, ((P[0] - A[0]) * ab[0] + (P[1] - A[1]) * ab[1]) / ll))
			T = tout
			da = base[(pi, a)][0] * T[0] + base[(pi, a)][1] * T[1]
			db = base[(pi, b)][0] * T[0] + base[(pi, b)][1] * T[1]
			v = vecs[(pi, i)]
			cur = v[0] * T[0] + v[1] * T[1]
			# hanya bila titik akan keluar dari rentang kedua tetangganya
			# (lengkung terlipat); selain itu bentuk lengkung dibiarkan
			pa = A[0] * T[0] + A[1] * T[1] + da
			pb = B[0] * T[0] + B[1] * T[1] + db
			pp = P[0] * T[0] + P[1] * T[1] + cur
			lo, hi = min(pa, pb), max(pa, pb)
			margin = 0.1 * (hi - lo)
			if lo + margin <= pp <= hi - margin:
				continue
			want = da * (1 - rel) + db * rel
			vecs[(pi, i)] = (v[0] + (want - cur) * T[0], v[1] + (want - cur) * T[1])


def _notch_depth(pts, types, i, side, grow):
	"""Kedalaman lekuk bila titik i ujung baji putih yang lancip (sudut
	cekung < 90 derajat saat menebal, cembung saat menipis): jarak dari titik
	ke garis mulut baji (antara titik on-curve tetangga), diukur sepanjang
	garis-bagi. None bila bukan lekuk lancip."""
	n = len(pts)
	tin = _neighbor_dir(pts, i, -1, True)
	tout = _neighbor_dir(pts, i, 1, True)
	if not tin or not tout:
		return None
	t1 = (-tin[0], -tin[1])
	turn = t1[0] * tout[1] - t1[1] * tout[0]
	concave = turn * side < -1e-9
	if concave != (grow > 0):
		return None
	# sudut antara kedua sisi (dari titik ke tetangga) lebih lancip dari 90 derajat
	if tin[0] * tout[0] + tin[1] * tout[1] <= 1e-6:
		return None
	on = [k for k in range(n) if types[k] != "offcurve"]
	if len(on) < 3:
		return None
	j = on.index(i)
	a, b = pts[on[j - 1]], pts[on[(j + 1) % len(on)]]
	bis = _unit(tin[0] + tout[0], tin[1] + tout[1])
	if bis is None:
		return None
	hit = _ray_hit(pts[i], bis, [(a[0], a[1], b[0], b[1])], eps=0.0)
	return hit


def _corner_vector(pts, i, closed, clamp, notch_clamp=None, side=1.0, grow=1.0):
	"""Vektor geser (panjang 1 di sisi lurus) ke kanan arah jalan path;
	di sudut dipakai miter agar kedua sisi bergeser sama jauh. Sudut yang
	akan masuk ke ruang putih (sudut cekung saat menebal, cembung saat
	menipis, mis. lekuk sambungan bahu n) dibatasi notch_clamp supaya
	lekuknya tetap ada dan titiknya tidak terlempar."""
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
	limit = clamp
	if notch_clamp is not None and tin and tout:
		t1 = (-tin[0], -tin[1])
		turn = t1[0] * tout[1] - t1[1] * tout[0]
		# side > 0: kanan arah jalan = luar tinta; sudut cekung = belok kanan
		concave = turn * side < -1e-9
		if concave == (grow > 0):
			# sudut siku (miter sqrt 2) tetap utuh; hanya sudut yang lebih
			# lancip yang dibatasi
			limit = min(clamp, max(notch_clamp, 1.4143))
	if l > limit:
		mx, my = mx * limit / l, my * limit / l
	return (mx, my)


def _flat_lines(paths, max_piece=20.0, meta=None):
	"""Potongan garis semua kontur. meta (list) diisi (path, node a, node b,
	t awal, t akhir) per potongan bila diberikan."""
	lines = []
	for pi, a, b, ctrl in path_curves(paths):
		length = sum(math.hypot(q[0] - p[0], q[1] - p[1]) for p, q in zip(ctrl, ctrl[1:]))
		k = 1 if len(ctrl) == 2 else max(8, int(math.ceil(length / max_piece)))
		prev = ctrl[0]
		for j in range(1, k + 1):
			q = _bez(ctrl, j / float(k))
			lines.append((prev[0], prev[1], q[0], q[1]))
			if meta is not None:
				meta.append((pi, a, b, (j - 1) / float(k), j / float(k)))
			prev = q
	return lines


def _ray_hit_info(p, d, lines, eps=1.0):
	"""Seperti _ray_hit, tapi juga mengembalikan indeks potongan & posisi u."""
	best = None
	px, py = p
	dx, dy = d
	for idx, (x1, y1, x2, y2) in enumerate(lines):
		ex, ey = x2 - x1, y2 - y1
		den = dx * ey - dy * ex
		if abs(den) < 1e-12:
			continue
		t = ((x1 - px) * ey - (y1 - py) * ex) / den
		if t <= eps or (best is not None and t >= best[0]):
			continue
		u = ((x1 - px) * dy - (y1 - py) * dx) / den
		if -1e-9 <= u <= 1 + 1e-9:
			best = (t, idx, u)
	return best


def protect_space(paths, vecs, pins, clamp=3.0, gap_ratio=0.2, min_gap=8.0, min_stroke=4.0,
		bias=None, limits=True):
	"""Batasi geseran supaya bentuk tidak rusak di bobot ekstrem (ubah vecs).
	- Menebal: sisi yang menghadap ruang putih tertutup (counter, mata e,
	  celah antar stem, aperture) hanya boleh memakan celah sampai tersisa
	  gap_ratio (min. min_gap unit); celah dibagi dua sisi. Kekurangannya
	  diambil alih sisi seberang goresan (biasanya sisi luar): tiap titik di
	  sana melihat titik yang ditembus sinarnya di sisi counter dan mengambil
	  kekurangan di situ (diinterpolasi sepanjang segmen), jadi sisi stem
	  tetap sejajar dan tebal yang diminta tercapai; huruf melebar.
	- Menipis: goresan tidak boleh lebih tipis dari min_stroke.
	- bias (x, y, io), masing-masing -1..1: arah perubahan tebal. x: -1 =
	  semua ke kiri, +1 = semua ke kanan; y: -1 = ke bawah, +1 = ke atas;
	  io: -1 = ke dalam (counter), +1 = ke luar. Bagian yang dikurangi dari
	  satu sisi diambil alih sisi seberang goresan, jadi total tebal tetap.
	limits=False: hanya bias, tanpa batas counter/tebal minimum."""
	polys = [list(p[2]) for p in paths]
	meta = []
	lines = _flat_lines(paths, meta=meta)
	if not lines:
		return
	ons = [((pi, i), pts[i]) for pi, (closed, types, pts) in enumerate(paths) if closed
		for i in range(len(pts)) if types[i] != "offcurve"]
	rays = {}
	fwd_hits = {}
	for (pi, i), p in ons:
		closed, types, pts = paths[pi]
		if (pi, i) not in vecs:
			continue
		side = path_side(polys, pi)
		dirs = []
		tin = _neighbor_dir(pts, i, -1, closed)
		tout = _neighbor_dir(pts, i, 1, closed)
		if tin:
			dirs.append((-tin[1] * side, tin[0] * side))
		if tout:
			dirs.append((tout[1] * side, -tout[0] * side))
		if len(dirs) == 2 and dirs[0][0] * dirs[1][0] + dirs[0][1] * dirs[1][1] > 0.98:
			dirs = [dirs[0]]
		out_rays = []
		for out in dirs:
			start = (p[0] + out[0] * 0.5, p[1] + out[1] * 0.5)
			fwd = _ray_hit_info(start, out, lines, eps=0.5)
			gap = fwd[0] if fwd else None
			back = _ray_hit_info(p, (-out[0], -out[1]), lines, eps=0.5)
			out_rays.append((out, gap, back))
			fwd_hits[((pi, i), out)] = fwd
		rays[(pi, i)] = out_rays

	def _vec_at_hit(hit):
		"""geseran yang DIMINTA tepi yang ditembus sinar (sebelum dibatasi;
		interpolasi dua titik ujung segmen)"""
		if not hit:
			return None
		pi_, a_, b_, t0_, t1_ = meta[hit[1]]
		va, vb = requested.get((pi_, a_)), requested.get((pi_, b_))
		if va is None or vb is None:
			return None
		t = t0_ + (t1_ - t0_) * hit[2]
		return (va[0] * (1 - t) + vb[0] * t, va[1] * (1 - t) + vb[1] * t)

	requested = dict(vecs)

	# 1) arah (bias) lalu batasi tiap titik; catat kekurangan (deficit,
	# bertanda: + = tambahan tebal yang belum terpakai, - = penipisan)
	deficit = {}
	bio = bias[2] if bias else 0.0
	for key, out_rays in rays.items():
		for out, gap, back in out_rays:
			vx, vy = vecs[key]
			grow = vx * out[0] + vy * out[1]  # + = menebal ke arah luar tinta
			if bio and abs(grow) > 1e-9 and back is not None:
				inside = gap is not None
				if (bio > 0 and inside) or (bio < 0 and not inside):
					# penerima (sisi seberang) dipegang zona: bagian vertikal
					# tidak bisa dipindah, jadi jangan dikurangi
					pi_, a_, b_, _, _ = meta[back[1]]
					held = (pi_, a_) in pins or (pi_, b_) in pins
					if held and abs(out[1]) > 0.5:
						pass
					else:
						red = abs(bio) * grow
						vx, vy = vx - out[0] * red, vy - out[1] * red
						vecs[key] = (vx, vy)
						deficit.setdefault(key, []).append((out, red))
						grow -= red
			if not limits:
				continue
			if grow > 0 and gap is not None:
				# celah dibagi sebanding gerakan sisi yang berhadapan (bukan
				# selalu 50/50: dengan arah, sisi seberang bisa diam)
				face = _vec_at_hit(fwd_hits.get((key, out)))
				face_grow = max(0.0, -(face[0] * out[0] + face[1] * out[1])) if face else grow
				share = grow / (grow + face_grow) if grow + face_grow > 1e-9 else 0.5
				limit = max(0.0, (gap - max(min_gap, gap_ratio * gap)) * share)
				if grow > limit:
					ex = grow - limit
					vecs[key] = (vx - out[0] * ex, vy - out[1] * ex)
					deficit.setdefault(key, []).append((out, ex))
			elif grow < 0 and back is not None:
				opp = _vec_at_hit(back)
				opp_in = max(0.0, opp[0] * out[0] + opp[1] * out[1]) if opp else -grow
				share = -grow / (-grow + opp_in) if (-grow + opp_in) > 1e-9 else 0.5
				limit = max(0.0, (back[0] - min_stroke) * share)
				if -grow > limit:
					ex = -grow - limit
					vecs[key] = (vx + out[0] * ex, vy + out[1] * ex)
	# sisi lurus: bila satu ujung dibatasi, ujung lainnya ikut dibatasi
	# searah normal sisi itu (sinar dari sudut kadang menyusur tepi lain
	# dan tidak menemukan celah, membuat sisi miring)
	for pi, (closed, types, pts) in enumerate(paths):
		n = len(pts)
		if not closed:
			continue
		side = path_side(polys, pi)
		for b in range(n):
			a = (b - 1) % n
			if types[b] != "line" or types[a] == "offcurve":
				continue
			ka, kb = (pi, a), (pi, b)
			if ka not in vecs or kb not in vecs:
				continue
			u = _unit(pts[b][0] - pts[a][0], pts[b][1] - pts[a][1])
			if u is None:
				continue
			nrm = (u[1] * side, -u[0] * side)
			limited = [k for k in (ka, kb) if any(o[0] * nrm[0] + o[1] * nrm[1] > 0.98 for o, _ in deficit.get(k, []))]
			if not limited:
				continue
			ga = vecs[ka][0] * nrm[0] + vecs[ka][1] * nrm[1]
			gb = vecs[kb][0] * nrm[0] + vecs[kb][1] * nrm[1]
			low = min(ga, gb)
			for k, g in ((ka, ga), (kb, gb)):
				if g > low + 1e-6:
					cut = g - low
					vecs[k] = (vecs[k][0] - nrm[0] * cut, vecs[k][1] - nrm[1] * cut)
					deficit.setdefault(k, []).append((nrm, cut))
	if not deficit:
		return

	def deficit_at(key, direction):
		"""kekurangan (bertanda) titik key yang arahnya berlawanan dengan direction"""
		total = 0.0
		for out, ex in deficit.get(key, []):
			if out[0] * direction[0] + out[1] * direction[1] < -0.5:
				total += ex
		return total

	# 2) sisi seberang mengambil alih kekurangan di titik yang ditembusnya
	add = {}
	for key, out_rays in rays.items():
		for out, gap, back in out_rays:
			if back is None:
				continue
			dist, idx, u = back
			pi, a, b, t0, t1 = meta[idx]
			t = t0 + (t1 - t0) * u
			ex = deficit_at((pi, a), out) * (1 - t) + deficit_at((pi, b), out) * t
			if abs(ex) <= 1e-9:
				continue
			tx, ty = out[0] * ex, (0.0 if key in pins else out[1] * ex)
			ox, oy = add.get(key, (0.0, 0.0))
			add[key] = (tx if abs(tx) > abs(ox) else ox, ty if abs(ty) > abs(oy) else oy)
	# sisi lurus tetap lurus: dua ujung segmen garis menerima pindahan yang
	# sama searah normal segmen itu (ambil yang terkecil)
	for pi, (closed, types, pts) in enumerate(paths):
		n = len(pts)
		if not closed:
			continue
		side = path_side(polys, pi)
		for b in range(n):
			a = (b - 1) % n
			if types[b] != "line" or types[a] == "offcurve":
				continue
			u = _unit(pts[b][0] - pts[a][0], pts[b][1] - pts[a][1])
			if u is None:
				continue
			nrm = (u[1] * side, -u[0] * side)
			ka, kb = (pi, a), (pi, b)
			pa = add.get(ka, (0.0, 0.0))
			pb = add.get(kb, (0.0, 0.0))
			ga = pa[0] * nrm[0] + pa[1] * nrm[1]
			gb = pb[0] * nrm[0] + pb[1] * nrm[1]
			if abs(ga - gb) < 1e-6:
				continue
			# ambil yang besarnya terkecil (0 bila tandanya berlawanan)
			low = 0.0 if ga * gb < 0 else (ga if abs(ga) < abs(gb) else gb)
			for key, g, cur in ((ka, ga, pa), (kb, gb, pb)):
				if abs(g - low) > 1e-9:
					cut = g - low
					add[key] = (cur[0] - nrm[0] * cut, cur[1] - nrm[1] * cut)
	# ekor / taji yang tumbuh dari sisi yang menerima pindahan ikut bergeser
	# (titik berurutan di kontur yang menghadap ke arah yang sama), supaya
	# tonjolannya tidak termakan stem yang menebal
	for pi, (closed, types, pts) in enumerate(paths):
		on = [k for k in range(len(pts)) if types[k] != "offcurve"]
		if not closed or len(on) < 3:
			continue
		for step in (1, -1):
			for j0, i0 in enumerate(on):
				src = add.get((pi, i0))
				if not src or math.hypot(*src) < 1e-6 or (pi, i0) not in rays:
					continue
				u = _unit(*src)
				# arah luar sumber: pindahan menebal searah luar, menipis berlawanan
				so = max((o for o, _, _ in rays[(pi, i0)]), key=lambda o: abs(o[0] * u[0] + o[1] * u[1]))
				if so[0] * u[0] + so[1] * u[1] < 0:
					u = so  # menipis: tetangga yang menghadap ke luar searah sumber
				j = j0
				while True:
					j = (j + step) % len(on)
					key = (pi, on[j])
					if key == (pi, i0) or key not in rays:
						break
					if not any(o[0] * u[0] + o[1] * u[1] > 0.2 for o, _, _ in rays[key]):
						break
					have = add.get(key, (0.0, 0.0))
					if abs(have[0] * src[0] + have[1] * src[1]) >= (src[0] ** 2 + src[1] ** 2) - 1e-6:
						break  # sudah menerima pindahan yang sama atau lebih
					add[key] = (src[0], 0.0 if key in pins else src[1])
	for key, (tx, ty) in add.items():
		vx, vy = vecs[key]
		vecs[key] = (vx + tx, vy + ty)


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


def offset_paths(paths, dx, dy, selected=None, clamp=3.0, weights=None, pins=None, protect=None,
		notch_clamp=1.0, terminals=None, bias=None):
	"""Geser kontur sejauh dx (arah x) dan dy (arah y) per sisi. Positif =
	lebih tebal: kontur luar melebar, counter menyempit (ditentukan dari
	tumpukan kontur, jadi tetap benar walau arah path belum dirapikan).
	paths: [(closed, types, pts)]. selected: set (indeks path, indeks node)
	untuk hanya menggeser titik terpilih. weights: {(path, node): (wx, wy)}
	pengali geser per titik (0 = titik diam), dari contrast_weights().
	pins: {(path, node): titik seberang atau None} dari zone_pins(): titik
	ini tidak bergeser vertikal, geser vertikalnya dipindahkan ke titik
	seberang goresan (jadi goresan tetap bertambah penuh, tumbuh ke dalam).
	protect: dict(gap_ratio, min_gap, min_stroke) -> lihat protect_space().
	Hasil: list pts baru per path."""
	polys = [list(pts) for _, _, pts in paths]
	vecs = {}
	outs = {}
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
			mx, my = _corner_vector(pts, i, closed, clamp, notch_clamp, s, dx + dy)
			wx, wy = weights.get((pi, i), (1.0, 1.0)) if weights else (1.0, 1.0)
			vx, vy = mx * dx * s * wx, my * dy * s * wy
			depth = _notch_depth(pts, types, i, s, dx + dy)
			if depth is not None:
				# ujung lekuk lancip (sambungan bahu n/m/h ke stem): geser paling
				# jauh setengah kedalaman lekuk, jadi lekuknya mengecil tapi tetap ada
				l = math.hypot(vx, vy)
				if l > 0.5 * depth:
					vx, vy = vx * 0.5 * depth / l, vy * 0.5 * depth / l
			vecs[(pi, i)] = (vx, vy)
			outs[(pi, i)] = (mx * s, my * s)
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
	if bias and (bias[0] or bias[1]):
		_apply_axis_bias(vecs, outs, bias[0], bias[1], pins or {})
	if bias and bias[2] and selected is not None:
		_apply_io_bias_selected(paths, vecs, outs, bias[2])
	io = (0.0, 0.0, bias[2]) if (bias and bias[2]) else None
	if (protect or io) and selected is None:
		protect_space(paths, vecs, pins or {}, clamp, bias=io, limits=bool(protect), **(protect or {}))
	if terminals and selected is None:
		stabilize_terminals(paths, vecs, pins or {}, terminals)
	if selected is None:
		_redistribute_tangential(paths, vecs)
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
	lines_cache = []
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
		if target is None:
			target = _pin_target_direct(paths, key, cand, ons, lines_cache)
		pins[key] = target
	return pins


def _pin_target_direct(paths, key, cand, ons, lines_cache):
	"""Cadangan untuk puncak/dasar lengkung yang halus: sinar tegak lurus
	langsung dari titik yang dipegang (ke bawah
	untuk zona atas, ke atas untuk zona bawah). Titik on-curve terdekat dari
	titik tembus menjadi pasangannya, kecuali goresannya ujung (sinar jauh
	lebih panjang dari lebar goresan, mis. dasar stem)."""
	if not lines_cache:
		lines_cache.append(_flat_lines(paths))
	lines = lines_cache[0]
	pi, i = key
	pts = paths[pi][2]
	tin = _neighbor_dir(pts, i, -1, True)
	tout = _neighbor_dir(pts, i, 1, True)
	if not tin or not tout or tin[0] * tout[0] + tin[1] * tout[1] > -0.97:
		return None  # hanya puncak/dasar lengkung yang halus; sudut stem tidak
	px, py = pts[i]
	top = cand[key][1]
	d = (0.0, -1.0) if top else (0.0, 1.0)
	hit = _ray_hit((px, py), d, lines, eps=0.5)
	if hit is None:
		return None
	hx, hy = px, py + d[1] * hit
	mx, my = px, py + d[1] * hit / 2.0
	w1 = _ray_hit((mx, my), (1.0, 0.0), lines, eps=0.0)
	w2 = _ray_hit((mx, my), (-1.0, 0.0), lines, eps=0.0)
	if w1 is not None and w2 is not None and hit > 2.5 * (w1 + w2):
		return None  # ujung goresan: menebal = memanjang, tidak ada pasangan
	best = None
	for k2, q in ons:
		if k2 == key or k2 in cand:
			continue
		dd = math.hypot(q[0] - hx, q[1] - hy)
		if dd <= max(15.0, 0.6 * hit) and (best is None or dd < best[0]):
			best = (dd, k2)
	return best[1] if best else None


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


DEFAULT_PROTECT = {"gap_ratio": 0.2, "min_gap": 8.0, "min_stroke": 4.0}
DEFAULT_TERMINAL = 40.0


def embolden(paths, width, add_x, add_y, keep_sidebearings=True, zones=None,
		selected=None, tolerance=25.0, weights=None, measure=None, protect=DEFAULT_PROTECT,
		terminals=DEFAULT_TERMINAL, bias=None):
	"""Tambah tebal stem tegak sebesar add_x dan stem datar sebesar add_y
	(negatif = lebih tipis). zones: garis metrik (0, x-height, cap height,
	...); titik yang menempel padanya tidak bergeser vertikal dan goresannya
	tumbuh ke dalam (lihat zone_pins). Hasil dict: pts (per path), width,
	anchor(x, y) -> (x, y)."""
	old = [list(p[2]) for p in paths]
	pins = zone_pins(paths, zones, tolerance, measure) if (zones and selected is None) else None
	new = offset_paths(paths, add_x / 2.0, add_y / 2.0, selected, weights=weights, pins=pins, protect=protect,
		terminals=terminals, bias=bias)
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
	grow_w = nw - ow
	bxv = max(-1.0, min(1.0, float(bias[0]))) if bias else 0.0
	if keep_sidebearings:
		# seimbang: LSB & RSB tetap, lebar bertambah. Ke kiri: tambahan tebal
		# memakan LSB (lebar tetap); ke kanan: memakan RSB.
		tx = (ox0 - nx0) - grow_w * max(0.0, -bxv)
		new_width = width + grow_w * (1.0 - abs(bxv))
	else:
		tx = 0.0
		new_width = width
	pts = [[(x + tx, y) for x, y in p] for p in new]
	x0 = nx0 + tx

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


# ---------------------------------------------------------------------------
# Audit kecocokan titik antar master: urutan kontur, arah, titik awal,
# titik lebih/kurang, tipe beda - dengan lokasi yang tepat

def reverse_path_data(types, pts):
	"""Balik arah path seperti Glyphs: titik on-curve membawa tipe segmen yang
	masuk ke dirinya, jadi setelah dibalik tipe itu pindah ke ujung lain."""
	n = len(types)
	on = [i for i, t in enumerate(types) if t != "offcurve"]
	if not on:
		return list(reversed(types)), list(reversed(pts))
	new = list(types)
	for k, i in enumerate(on):
		new[i] = types[on[(k + 1) % len(on)]]
	return list(reversed(new)), list(reversed(pts))


def _norm_pts(pts, box):
	x0, y0, x1, y1 = box
	w = (x1 - x0) or 1.0
	h = (y1 - y0) or 1.0
	return [((x - x0) / w, (y - y0) / h) for x, y in pts]


def _box(pts):
	xs = [p[0] for p in pts]
	ys = [p[1] for p in pts]
	return (min(xs), min(ys), max(xs), max(ys)) if xs else (0, 0, 1, 1)


def match_contours(ref_paths, paths):
	"""Pasangkan kontur master lain dengan kontur referensi berdasarkan letak
	(relatif terhadap glyph), ukuran, dan jumlah/tipe titik - bukan nomor
	urutnya. Hasil: list indeks kontur lain per kontur referensi (None bila
	tidak ada pasangan)."""
	gb_ref = _box([p for _, _, pts in ref_paths for p in pts])
	gb = _box([p for _, _, pts in paths for p in pts])

	def desc(paths_, gbox):
		out = []
		for closed, types, pts in paths_:
			b = _box(_norm_pts(pts, gbox)) if pts else (0, 0, 0, 0)
			out.append(((b[0] + b[2]) / 2.0, (b[1] + b[3]) / 2.0, b[2] - b[0], b[3] - b[1], len(pts),
				sum(1 for t in types if t == "offcurve")))
		return out
	A, B = desc(ref_paths, gb_ref), desc(paths, gb)
	costs = []
	for i, a in enumerate(A):
		for j, b in enumerate(B):
			c = (math.hypot(a[0] - b[0], a[1] - b[1]) + abs(a[2] - b[2]) + abs(a[3] - b[3])
				+ 0.02 * abs(a[4] - b[4]) + 0.02 * abs(a[5] - b[5]))
			costs.append((c, i, j))
	costs.sort()
	out = [None] * len(A)
	used = set()
	for c, i, j in costs:
		if out[i] is None and j not in used:
			out[i] = j
			used.add(j)
	return out


def _align_dp(A, B, gap=0.25):
	"""A, B: [(tipe, (x, y) ternormalisasi)]. Penjajaran urutan (edit
	distance). Hasil (biaya, [(i atau None, j atau None)])."""
	n, m = len(A), len(B)
	INF = float("inf")
	D = [[INF] * (m + 1) for _ in range(n + 1)]
	D[0][0] = 0.0
	for i in range(n + 1):
		for j in range(m + 1):
			d = D[i][j]
			if d == INF:
				continue
			if i < n and j < m:
				(ta, pa), (tb, pb) = A[i], B[j]
				c = math.hypot(pa[0] - pb[0], pa[1] - pb[1]) + (0.0 if ta == tb else 0.3)
				if d + c < D[i + 1][j + 1]:
					D[i + 1][j + 1] = d + c
			if i < n and d + gap < D[i + 1][j]:
				D[i + 1][j] = d + gap
			if j < m and d + gap < D[i][j + 1]:
				D[i][j + 1] = d + gap
	pairs = []
	i, j = n, m
	while i > 0 or j > 0:
		if i > 0 and j > 0:
			(ta, pa), (tb, pb) = A[i - 1], B[j - 1]
			c = math.hypot(pa[0] - pb[0], pa[1] - pb[1]) + (0.0 if ta == tb else 0.3)
			if abs(D[i][j] - (D[i - 1][j - 1] + c)) < 1e-9:
				pairs.append((i - 1, j - 1))
				i, j = i - 1, j - 1
				continue
		if i > 0 and abs(D[i][j] - (D[i - 1][j] + gap)) < 1e-9:
			pairs.append((i - 1, None))
			i -= 1
		else:
			pairs.append((None, j - 1))
			j -= 1
	pairs.reverse()
	return D[n][m], pairs


def align_contour(ref_types, ref_pts, types, pts):
	"""Bandingkan satu kontur dengan kontur referensi. Hasil dict:
	reversed (arah terbalik), start (indeks titik di kontur ini yang
	seharusnya jadi titik awal; None bila sudah sama), pairs [(i_ref, j)]
	(j = indeks asli di kontur ini), extra [j], missing [i_ref],
	type_diff [(i_ref, j)], compatible (bisa diperbaiki otomatis)."""
	n, m = len(ref_types), len(types)
	A = list(zip(ref_types, _norm_pts(ref_pts, _box(ref_pts))))
	best = None
	for rev in (False, True):
		t2, p2 = (reverse_path_data(types, pts) if rev else (list(types), list(pts)))
		# indeks asli untuk tiap posisi urutan t2
		idx = list(range(m))[::-1] if rev else list(range(m))
		normB = _norm_pts(p2, _box(p2))
		if n == m:
			for r in rotation_candidates(ref_types, t2):
				c = sum(math.hypot(A[i][1][0] - normB[(i + r) % m][0], A[i][1][1] - normB[(i + r) % m][1]) for i in range(n))
				if best is None or c < best[0] - 1e-9:
					best = (c, rev, r, [(i, idx[(i + r) % m]) for i in range(n)], True)
		if best is not None and best[4]:
			continue
		# jumlah/tipe beda: coba beberapa titik awal yang paling dekat posisinya
		starts = sorted(range(m), key=lambda j: math.hypot(normB[j][0] - A[0][1][0], normB[j][1] - A[0][1][1]))[:6]
		for r in starts:
			Bseq = [(t2[(j + r) % m], normB[(j + r) % m]) for j in range(m)]
			c, pairs = _align_dp(A, Bseq)
			mapped = [(i, None if j is None else idx[(j + r) % m]) for i, j in pairs]
			if best is None or (not best[4] and c < best[0]):
				best = (c, rev, r, mapped, False)
	c, rev, r, pairs, exact = best
	extra = [j for i, j in pairs if i is None]
	missing = [i for i, j in pairs if j is None]
	type_diff = [(i, j) for i, j in pairs if i is not None and j is not None and ref_types[i] != types[j]
		and not rev]
	start = None
	if exact:
		# titik awal Glyphs = titik on-curve terakhir di daftar; laporkan
		# titik kontur ini yang sepadan dengan titik awal referensi
		ref_start = max(i for i in range(n) if ref_types[i] != "offcurve")
		cur_start = max(j for j in range(m) if types[j] != "offcurve")
		want = [j for i, j in pairs if i == ref_start][0]
		if rev or want != cur_start:
			start = want
	return {"reversed": rev, "start": start, "pairs": pairs, "extra": sorted(extra),
		"missing": sorted(missing), "type_diff": type_diff, "compatible": exact}


def diff_paths(ref_paths, paths):
	"""Bandingkan semua kontur satu master dengan referensi. Hasil dict:
	order (list indeks kontur lain per kontur ref), unmatched_ref,
	unmatched_other, contours [{ref, other, ...hasil align_contour}]."""
	order = match_contours(ref_paths, paths)
	contours = []
	for i, j in enumerate(order):
		if j is None:
			continue
		rc, rt, rp = ref_paths[i]
		oc, ot, op = paths[j]
		d = align_contour(rt, rp, ot, op)
		d.update({"ref": i, "other": j, "closed_diff": bool(rc) != bool(oc)})
		contours.append(d)
	used = set(j for j in order if j is not None)
	return {"order": order, "unmatched_ref": [i for i, j in enumerate(order) if j is None],
		"unmatched_other": [j for j in range(len(paths)) if j not in used], "contours": contours}


def diff_items(diff, ref_paths, paths, ref_name, name):
	"""Masalah per item: {text, nodes [(kontur, titik) di master ini],
	ref_nodes [(kontur, titik) di referensi], kind}."""
	out = []

	def item(kind, text, nodes=(), ref_nodes=()):
		out.append({"kind": kind, "text": text, "nodes": list(nodes), "ref_nodes": list(ref_nodes)})
	if len(ref_paths) != len(paths):
		item("count", "%s punya %d kontur, %s %d" % (name, len(paths), ref_name, len(ref_paths)))
	for i in diff["unmatched_ref"]:
		item("contour", "kontur %d di %s tidak ada pasangannya di %s" % (i, ref_name, name),
			ref_nodes=[(i, k) for k in range(len(ref_paths[i][2]))])
	for j in diff["unmatched_other"]:
		item("contour", "kontur %d di %s tidak ada pasangannya di %s" % (j, name, ref_name),
			nodes=[(j, k) for k in range(len(paths[j][2]))])
	for d in diff["contours"]:
		i, j = d["ref"], d["other"]
		tag = "%s kontur %d" % (name, j)
		ot, rt = paths[j][1], ref_paths[i][1]
		on_j = [k for k in range(len(ot)) if ot[k] != "offcurve"]
		if i != j:
			item("order", "%s = kontur %d di %s (urutan kontur beda)" % (tag, i, ref_name), nodes=[(j, k) for k in on_j[:1]])
		if d["closed_diff"]:
			item("closed", "%s: terbuka/tertutup beda" % tag)
		if d["reversed"]:
			item("direction", "%s: arah titik terbalik" % tag, nodes=[(j, k) for k in on_j])
		if d["start"] is not None:
			item("start", "%s: titik awal beda, seharusnya %d:%d" % (tag, j, d["start"]), nodes=[(j, d["start"])])
		for jj in d["extra"]:
			x, y = paths[j][2][jj]
			item("extra", "%s: titik LEBIH %d:%d (%s) di %s, %s" % (tag, j, jj, ot[jj], fmt_num(round(x)), fmt_num(round(y))),
				nodes=[(j, jj)])
		for ii in d["missing"]:
			x, y = ref_paths[i][2][ii]
			item("missing", "%s: KURANG titik seperti %s %d:%d (%s) di %s, %s" % (tag, ref_name, i, ii, rt[ii],
				fmt_num(round(x)), fmt_num(round(y))), ref_nodes=[(i, ii)])
		for ii, jj in d["type_diff"]:
			item("type", "%s: titik %d:%d %s, di %s %d:%d %s" % (tag, j, jj, ot[jj], ref_name, i, ii, rt[ii]),
				nodes=[(j, jj)], ref_nodes=[(i, ii)])
	return out


def describe_diff(diff, ref_paths, paths, ref_name, name):
	return [x["text"] for x in diff_items(diff, ref_paths, paths, ref_name, name)]
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


def _sig_extras(ref_layer, layer, ref_name, name):
	"""Masalah komponen & anchor (bagian non-kontur)."""
	ref, sig = layer_signature(ref_layer), layer_signature(layer)
	ref["paths"] = sig["paths"] = []
	return compare_signatures(ref, sig, ref_name, name)


def glyph_issue_items(glyph, font):
	"""Semua masalah kecocokan antar master dengan lokasi titiknya.
	[{text, kind, source, layer, nodes, ref_nodes}]: nodes = (kontur, titik)
	di layer `layer` (master `source`), ref_nodes di master referensi."""
	srcs = glyph_sources(glyph, font)
	if len(srcs) < 2:
		return []
	ref_name, _, ref_layer = srcs[0]
	ref_paths = layer_paths(ref_layer)
	out = []
	for name, _, layer in srcs[1:]:
		paths = layer_paths(layer)
		diff = diff_paths(ref_paths, paths)
		for it in diff_items(diff, ref_paths, paths, ref_name, name):
			it.update({"source": name, "layer": layer, "ref_layer": ref_layer})
			out.append(it)
		for text in _sig_extras(ref_layer, layer, ref_name, name):
			out.append({"text": text, "kind": "other", "source": name, "layer": layer, "ref_layer": ref_layer,
				"nodes": [], "ref_nodes": []})
	return out


def check_glyph(glyph, font):
	return [it["text"] for it in glyph_issue_items(glyph, font)]


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


def _reorder_paths(layer, order):
	"""order[i] = indeks path sekarang yang harus berada di posisi i. Komponen
	tetap di tempatnya di daftar shapes."""
	paths = list(layer.paths)
	new_paths = [paths[j] for j in order] + [p for k, p in enumerate(paths) if k not in order]
	shapes = list(layer.shapes)
	it = iter(new_paths)
	layer.shapes = [next(it) if hasattr(sh, "nodes") else sh for sh in shapes]


def fix_glyph(glyph, font):
	"""Samakan urutan kontur, arah, dan titik awal semua master dengan master
	pertama. Titik lebih/kurang tidak bisa ditebak, jadi hanya dilaporkan."""
	srcs = glyph_sources(glyph, font)
	if len(srcs) < 2:
		return []
	ref_name, _, ref_layer = srcs[0]
	ref_paths = layer_paths(ref_layer)
	changes = []
	for name, _, layer in srcs[1:]:
		diff = diff_paths(ref_paths, layer_paths(layer))
		order = diff["order"]
		if None not in order and not diff["unmatched_other"] and order != list(range(len(order))):
			_reorder_paths(layer, order)
			changes.append("%s: urutan kontur disamakan" % name)
			diff = diff_paths(ref_paths, layer_paths(layer))
		for d in diff["contours"]:
			ci = d["other"]
			p = layer.paths[ci]
			if not d["compatible"]:
				left = []
				if d["extra"]:
					left.append("titik lebih " + ", ".join("%d:%d" % (ci, j) for j in d["extra"]))
				if d["missing"]:
					left.append("kurang %d titik" % len(d["missing"]))
				changes.append("%s kontur %d: perlu diperbaiki manual (%s)" % (name, ci, "; ".join(left) or "tipe titik beda"))
				continue
			if d["reversed"]:
				p.reverse()
				changes.append("%s kontur %d: arah dibalik" % (name, ci))
			rt, rpts = ref_paths[d["ref"]][1], ref_paths[d["ref"]][2]
			t, pts = _types_pts(p)
			r = best_rotation(rt, rpts, t, pts)
			if r:
				if _rotate_path(p, r):
					changes.append("%s kontur %d: titik awal digeser" % (name, ci))
				else:
					changes.append("%s kontur %d: gagal menggeser titik awal" % (name, ci))
	return changes


def select_nodes(layer, ids):
	"""Pilih titik (kontur, titik) di layer supaya mudah ditemukan."""
	nodes = []
	for ci, ni in ids:
		try:
			nodes.append(layer.paths[ci].nodes[ni])
		except Exception:
			pass
	try:
		layer.selection = nodes
	except Exception:
		for n in nodes:
			try:
				n.selected = True
			except Exception:
				pass
	return len(nodes)


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


# ---------------------------------------------------------------------------
# Tebal / tipis

def master_zones(master):
	zones = [0.0]
	for attr in ("xHeight", "capHeight", "ascender", "descender"):
		try:
			zones.append(float(getattr(master, attr)))
		except Exception:
			pass
	return zones


def _selected_nodes(layer):
	"""set (indeks path, indeks node) dari on-curve terpilih."""
	sel = set()
	for pi, p in enumerate(layer.paths):
		for ni, n in enumerate(p.nodes):
			if node_type(n) != "offcurve" and getattr(n, "selected", False):
				sel.add((pi, ni))
	return sel


def layer_paths(layer):
	return [(bool(getattr(p, "closed", True)), [node_type(n) for n in p.nodes], [_xy(n) for n in p.nodes])
		for p in layer.paths]


def measure_layer(layer, decompose=True):
	"""Ukur ketebalan garis layer (komponen ikut diukur bila decompose)."""
	return measure_paths(layer_paths(_decomposed(layer) if decompose else layer))


def embolden_layer(layer, add_x, add_y, keep_sidebearings=True, zones=None, selected_only=False,
		mode="uniform", thin_limit=None, thick_limit=None, protect=DEFAULT_PROTECT, terminals=DEFAULT_TERMINAL,
		bias=None):
	"""Tebalkan (positif) / tipiskan (negatif) satu layer tanpa mengubah
	jumlah atau urutan titik. mode 'keepthin': garis setipis thin_limit
	tidak berubah, garis setebal thick_limit ke atas bertambah penuh
	('keepthick' kebalikannya). Hasil: jumlah titik yang digeser."""
	paths = layer_paths(layer)
	if not paths:
		return 0
	weights = None
	measure = measure_paths(paths) if (mode != "uniform" or zones) else None
	if mode != "uniform":
		if thin_limit is None or thick_limit is None:
			raise ValueError("Isi batas tipis dan batas tebal (atau klik Scan ketebalan).")
		weights = contrast_weights(measure, float(thin_limit), float(thick_limit), mode)
	selected = None
	if selected_only:
		selected = _selected_nodes(layer)
		if not selected:
			return 0
	res = embolden(paths, float(layer.width), float(add_x), float(add_y),
		keep_sidebearings, zones, selected, weights=weights, measure=measure, protect=protect, terminals=terminals,
		bias=bias)
	moved = 0
	for p, pts in zip(layer.paths, res["pts"]):
		for n, (x, y) in zip(p.nodes, pts):
			if _xy(n) != (x, y):
				n.position = NSPoint(x, y)
				moved += 1
	if selected is None:
		for a in layer.anchors:
			x, y = res["anchor"](a.position.x, a.position.y)
			a.position = NSPoint(x, y)
		layer.width = res["width"]
	return moved


def embolden_master(font, src, add_x, add_y, target=None, name=None, axis_idx=None,
		axis_value=None, keep_sidebearings=True, keep_heights=True, glyphs=None, kerning=True,
		mode="uniform", thin_limit=None, thick_limit=None, protect=DEFAULT_PROTECT, terminals=DEFAULT_TERMINAL,
		bias=None):
	"""Isi master target (atau master baru `name`) dengan salinan master
	sumber yang ditebalkan/ditipiskan. target == src berarti diubah di tempat.
	Hasil: (master, jumlah glyph diubah)."""
	if target is None:
		target = _new_master_from(font, src, name or "%s %+d" % (src.name, int(add_x)))
		vals = axis_values(src, font)
		if axis_idx is not None and axis_value is not None:
			vals[axis_idx] = float(axis_value)
		set_axis_values(target, font, vals)
		_set_metrics(target, _metrics(src))
		if kerning:
			_set_kerning(font, target.id, _kerning_of(font, src.id))
		glyphs = None  # master baru: semua glyph harus terisi
	zones = master_zones(src) if keep_heights else None
	todo = glyphs if glyphs is not None else list(font.glyphs)
	old_width = {}
	for g in font.glyphs:
		s = g.layers[src.id]
		if s is not None:
			old_width[g.name] = float(s.width)
	done = 0
	composites = []
	for g in todo:
		s = g.layers[src.id]
		if s is None:
			continue
		layer = s if target is src else _put_layer(g, target.id, s)
		if not layer.paths and layer.components:
			composites.append(layer)
		elif embolden_layer(layer, add_x, add_y, keep_sidebearings, zones, False, mode, thin_limit, thick_limit, protect,
				terminals, bias):
			done += 1
	# Glyph yang hanya berisi komponen: lebar ikut glyph dasarnya, komponen
	# berikutnya (aksen) digeser setengahnya agar tetap di tengah.
	if keep_sidebearings:
		for layer in composites:
			base = layer.components[0].componentName
			bg = font.glyphs[base]
			bl = bg.layers[target.id] if bg is not None else None
			if bl is None or base not in old_width:
				continue
			delta = float(bl.width) - old_width[base]
			if abs(delta) < 1e-9:
				continue
			layer.width = float(layer.width) + delta
			for c in list(layer.components)[1:]:
				c.position = NSPoint(c.position.x + delta / 2.0, c.position.y)
			done += 1
	return target, done


SCAN_SAMPLE_NAMES = set(list("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ") + [
	"zero", "one", "two", "three", "four", "five", "six", "seven", "eight", "nine"])


def scan_thickness(font, master, glyphs=None):
	"""Ukur tertipis/tertebal tiap glyph di satu master. Hasil:
	(baris per glyph, ringkasan). Batas otomatis dihitung dari huruf dasar
	(a-z, A-Z, 0-9) bila ada, supaya simbol aneh tidak mengacaukan."""
	rows = []
	for g in (glyphs if glyphs is not None else font.glyphs):
		layer = g.layers[master.id]
		if layer is None or (not layer.paths and not layer.components):
			continue
		m = measure_layer(layer)
		sm = thickness_summary(m)
		if sm["all"][0] is None:
			continue
		rows.append({"name": g.name, "thin": sm["all"][0], "thick": sm["all"][1],
			"v": sm["v"], "h": sm["h"]})
	basis = [r for r in rows if r["name"] in SCAN_SAMPLE_NAMES] or rows
	lo, hi = auto_thresholds([(r["thin"], r["thick"]) for r in basis])
	summary = {"lo": lo, "hi": hi}
	if rows:
		summary["thinnest"] = min(rows, key=lambda r: r["thin"])
		summary["thickest"] = max(rows, key=lambda r: r["thick"])
	return rows, summary
# ======================================================================
# vs_reporter.py
# Variable Studio Preview - reporter (View > Show Variable Studio):
# ID titik "kontur:indeks", bayangan semua master, jalur titik antar master,
# dan bentuk hasil interpolasi di lokasi yang dipilih di jendela Variable Studio.

import objc
from GlyphsApp import Glyphs
from GlyphsApp.plugins import ReporterPlugin
from AppKit import NSColor, NSBezierPath, NSPoint, NSRect

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
	def _diff_items(self, layer):
		"""Masalah kecocokan untuk glyph ini, di-cache selama bentuk semua
		master sama."""
		font = self._font(layer)
		glyph = layer.parent
		if font is None or glyph is None:
			return []
		srcs = glyph_sources(glyph, font)
		if len(srcs) < 2:
			return []
		key = (glyph.name, tuple((n, tuple((c, tuple(t), tuple(p)) for c, t, p in layer_paths(l))) for n, _, l in srcs))
		cache = getattr(self, "_diff_cache", None)
		if cache is None or cache[0] != key:
			try:
				items = glyph_issue_items(glyph, font)
			except Exception:
				items = []
			cache = (key, items)
			self._diff_cache = cache
		return cache[1]

	@objc.python_method
	def _mark(self, x, y, scale, rgb, text, dashed=False):
		r = 9.0 / scale
		bp = NSBezierPath.bezierPathWithOvalInRect_(NSRect((x - r, y - r), (2 * r, 2 * r)))
		bp.setLineWidth_(2.0 / scale)
		if dashed:
			bp.setLineDash_count_phase_([3.0 / scale, 2.0 / scale], 2, 0)
		_color(rgb).set()
		bp.stroke()
		if text:
			self.drawTextAtPoint(text, NSPoint(x + r * 1.3, y - r * 1.6), fontSize=9.0, fontColor=_color(rgb), align="topleft")

	@objc.python_method
	def _draw_diff(self, layer, scale):
		items = self._diff_items(layer)
		if not items:
			return
		lid = getattr(layer, "layerId", None)
		RED, ORANGE, BLUE, PURPLE = (0.90, 0.15, 0.15), (0.95, 0.55, 0.10), (0.15, 0.45, 0.95), (0.60, 0.25, 0.85)

		def same(l):
			return l is layer or (lid is not None and getattr(l, "layerId", None) == lid)

		def pos(l, ci, ni):
			try:
				return _xy(l.paths[ci].nodes[ni])
			except Exception:
				return None
		for it in items:
			kind = it["kind"]
			here = same(it["layer"])
			on_ref = same(it["ref_layer"])
			if not here and not on_ref:
				continue
			src = it["source"]
			if here:
				for ci, ni in it["nodes"]:
					p = pos(layer, ci, ni)
					if p is None:
						continue
					if kind == "extra":
						self._mark(p[0], p[1], scale, RED, "lebih (tidak ada di master ref)")
					elif kind == "type":
						self._mark(p[0], p[1], scale, ORANGE, "tipe beda")
					elif kind == "start":
						self._mark(p[0], p[1], scale, BLUE, "jadikan titik awal")
				if kind == "missing":
					for ci, ni in it["ref_nodes"]:
						p = pos(it["ref_layer"], ci, ni)
						if p is not None:
							self._mark(p[0], p[1], scale, RED, "kurang titik (ref %d:%d)" % (ci, ni), dashed=True)
				if kind in ("direction", "order", "contour") and it["nodes"]:
					p = pos(layer, *it["nodes"][0])
					if p is not None:
						label = {"direction": "arah terbalik", "order": "urutan kontur beda",
							"contour": "kontur tanpa pasangan"}[kind]
						self.drawTextAtPoint(label, NSPoint(p[0], p[1] + 14.0 / scale), fontSize=10.0,
							fontColor=_color(PURPLE), align="bottomleft")
			if on_ref and kind in ("missing", "type"):
				for ci, ni in it["ref_nodes"]:
					p = pos(layer, ci, ni)
					if p is not None:
						self._mark(p[0], p[1], scale, RED if kind == "missing" else ORANGE,
							("tidak ada di %s" if kind == "missing" else "tipe beda di %s") % src)

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
		if pref_bool("showDiff", True):
			self._draw_diff(layer, scale)
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
