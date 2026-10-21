# encoding: utf-8
# Variable Studio - operasi pada font Glyphs 3 (axis, master, instance,
# italic, interpolasi, kompatibilitas). Dipakai oleh jendela dan reporter.

from __future__ import division, print_function, unicode_literals
import copy
import json
import math
import uuid

from GlyphsApp import Glyphs, GSAxis, GSInstance, GSCustomParameter
from Foundation import NSPoint
from vs_core import *  # BUILD:STRIP
from vs_core import _xy  # BUILD:STRIP

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
