const add = (a, b) => a.map((value, i) => value + b[i]);
const sub = (a, b) => a.map((value, i) => value - b[i]);
const mul = (a, factor) => a.map(value => value * factor);
const dot = (a, b) => a.reduce((sum, value, i) => sum + value * b[i], 0);
const cross = (a, b) => [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
const unit = a => mul(a, 1 / Math.hypot(...a));
const center = points => mul(points.reduce(add, [0, 0, 0]), 1 / points.length);
const normal = points => unit(cross(sub(points[1], points[0]), sub(points[2], points[0])));
function basis(n) {
    const u = unit(cross(Math.abs(n[1]) > .95 ? [0, 0, 1] : [0, 1, 0], n));
    return [u, cross(n, u), n];
}
function polygonCenter(points) {
    let total = 0;
    let sum = [0, 0, 0];
    for (let i = 1; i < points.length - 1; i++) {
        const weight = Math.hypot(...cross(sub(points[i], points[0]), sub(points[i + 1], points[0])));
        sum = add(sum, mul(center([points[0], points[i], points[i + 1]]), weight));
        total += weight;
    }
    return mul(sum, 1 / total);
}
function clearance(points, point) {
    return Math.min(...points.map((a, i) => {
        const edge = sub(points[(i + 1) % points.length], a);
        return Math.hypot(...cross(edge, sub(point, a))) / Math.hypot(...edge);
    }));
}
function mesh(vertices, faces) {
    const radius = Math.max(...vertices.map(point => Math.hypot(...point)));
    vertices = vertices.map(point => mul(point, 1 / radius));
    return {
        vertices,
        faces: faces.map((indices, index) => {
            let points = indices.map(i => vertices[i]);
            if (dot(normal(points), center(points)) < 0) { indices = [...indices].reverse(); points = indices.map(i => vertices[i]); }
            const n = normal(points);
            const c = polygonCenter(points);
            return { indices, normal: n, center: c, basis: basis(n), radius: clearance(points, c), label: index + 1 };
        }),
    };
}
function dual(source) {
    const vertices = source.faces.map(face => mul(face.normal, 1 / dot(face.normal, face.center)));
    const faces = source.vertices.map((vertex, index) => {
        const [u, v] = basis(unit(vertex));
        return source.faces.map((face, i) => face.indices.includes(index) ? i : -1).filter(i => i >= 0)
            .sort((a, b) => Math.atan2(dot(vertices[a], v), dot(vertices[a], u)) - Math.atan2(dot(vertices[b], v), dot(vertices[b], u)));
    });
    return mesh(vertices, faces);
}
const cache = new Map();
export function createDieMesh(sides) {
    if (cache.has(sides)) return cache.get(sides);
    let shape;
    if (sides === 2) {
        const vertices = [];
        const segments = 40;
        for (const z of [.11, -.11]) for (let i = 0; i < segments; i++) vertices.push([Math.cos(i * 2 * Math.PI / segments), Math.sin(i * 2 * Math.PI / segments), z]);
        const faces = [Array.from({ length: segments }, (_, i) => i), Array.from({ length: segments }, (_, i) => i + segments)];
        for (let i = 0; i < segments; i++) { const next = (i + 1) % segments; faces.push([i, next, next + segments, i + segments]); }
        shape = mesh(vertices, faces);
        shape.faces.slice(2).forEach(face => face.label = 0);
    } else if (sides === 4) {
        shape = mesh([[1, 1, 1], [-1, -1, 1], [-1, 1, -1], [1, -1, -1]], [[0, 1, 2], [0, 3, 1], [0, 2, 3], [1, 3, 2]]);
    } else if (sides === 6) {
        shape = mesh([[-1, -1, -1], [1, -1, -1], [1, 1, -1], [-1, 1, -1], [-1, -1, 1], [1, -1, 1], [1, 1, 1], [-1, 1, 1]], [[4, 5, 6, 7], [1, 0, 3, 2], [0, 4, 7, 3], [5, 1, 2, 6], [3, 7, 6, 2], [0, 1, 5, 4]]);
    } else if (sides === 8) {
        const vertices = [[1, 0, 0], [-1, 0, 0], [0, 1, 0], [0, -1, 0], [0, 0, 1], [0, 0, -1]];
        const faces = [];
        for (const x of [0, 1]) for (const y of [2, 3]) for (const z of [4, 5]) faces.push([x, y, z]);
        shape = mesh(vertices, faces);
    } else if (sides === 10) {
        // Dual of a pentagonal antiprism: ten actual kite-shaped faces.
        const vertices = [];
        for (const [z, offset] of [[.6, 0], [-.6, Math.PI / 5]]) for (let i = 0; i < 5; i++) vertices.push([Math.cos(i * Math.PI * 2 / 5 + offset), Math.sin(i * Math.PI * 2 / 5 + offset), z]);
        const faces = [[0, 1, 2, 3, 4], [5, 6, 7, 8, 9]];
        for (let i = 0; i < 5; i++) { const next = (i + 1) % 5; faces.push([i, i + 5, next], [next, i + 5, next + 5]); }
        shape = dual(mesh(vertices, faces));
    } else if (sides === 20 || sides === 12) {
        const p = (1 + Math.sqrt(5)) / 2;
        shape = mesh([[-1, p, 0], [1, p, 0], [-1, -p, 0], [1, -p, 0], [0, -1, p], [0, 1, p], [0, -1, -p], [0, 1, -p], [p, 0, -1], [p, 0, 1], [-p, 0, -1], [-p, 0, 1]], [[0, 11, 5], [0, 5, 1], [0, 1, 7], [0, 7, 10], [0, 10, 11], [1, 5, 9], [5, 11, 4], [11, 10, 2], [10, 7, 6], [7, 1, 8], [3, 9, 4], [3, 4, 2], [3, 2, 6], [3, 6, 8], [3, 8, 9], [4, 9, 5], [2, 4, 11], [6, 2, 10], [8, 6, 7], [9, 8, 1]]);
        if (sides === 12) shape = dual(shape);
    } else throw new RangeError('Unsupported die');
    if (sides === 6) shape.faces.forEach((face, index) => face.label = [1, 6, 2, 5, 3, 4][index]);
    cache.set(sides, shape);
    return shape;
}
function rotate([x, y, z], [rx, ry, rz]) {
    [y, z] = [y * Math.cos(rx) - z * Math.sin(rx), y * Math.sin(rx) + z * Math.cos(rx)];
    [x, z] = [x * Math.cos(ry) + z * Math.sin(ry), -x * Math.sin(ry) + z * Math.cos(ry)];
    return [x * Math.cos(rz) - y * Math.sin(rz), x * Math.sin(rz) + y * Math.cos(rz), z];
}
const colors = { 2: [233, 181, 71], 4: [90, 205, 180], 6: [240, 228, 202], 8: [102, 175, 238], 10: [185, 139, 239], 12: [238, 124, 145], 20: [178, 220, 107] };

export function diePose(sides, value, progress = 1) {
    const shape = createDieMesh(sides);
    const selected = shape.faces.find(face => face.label === value);
    if (!selected) throw new RangeError('Invalid die result');
    const t = Math.max(0, Math.min(1, progress));
    const spin = (1 - t) ** 3;
    // A tetrahedron rests on a face: its result belongs to the upper vertex.
    const axes = sides === 4 ? basis(unit(shape.vertices[value - 1])) : selected.basis;
    let yaw = .45;
    if (sides === 4) {
        const anchor = shape.vertices.find((_, index) => index !== value - 1);
        yaw = Math.atan2(-dot(anchor, axes[0]), -dot(anchor, axes[1])) + .1;
    }
    function world(point) {
        const aligned = [dot(point, axes[0]), dot(point, axes[2]), -dot(point, axes[1])];
        return rotate(aligned, [spin * Math.PI * (sides === 2 ? 6 : 4), yaw + spin * Math.PI * 4, spin * Math.PI * 3]);
    }
    const view = point => rotate(world(point), [.7, 0, 0]);
    return { shape, selected, world, view, t };
}

export function faceLabels(shape, face, sides, result) {
    const points = face.indices.map(index => shape.vertices[index]);
    if (!face.label) return [];
    if (sides === 4) return face.indices.map(index => {
        const position = add(mul(face.center, .55), mul(shape.vertices[index], .45));
        const v = unit(sub(shape.vertices[index], face.center));
        const u = cross(v, face.normal);
        return { text: String(index + 1), position, basis: [u, v, face.normal], radius: clearance(points, position), selected: index + 1 === result };
    });
    return [{ text: sides === 2 ? (face.label === 1 ? 'K' : 'Z') : String(face.label), position: face.center, radius: face.radius, selected: face.label === result }];
}

export function paintDie(ctx, width, height, sides, value, progress = 1) {
    const { shape, view, t } = diePose(sides, value, progress);
    const bounce = Math.sin(t * Math.PI) * 26 + Math.abs(Math.sin(t * Math.PI * 3)) * 9 * (1 - t);
    const scale = Math.min(width / 3.2, height / 2.85);
    const cx = width / 2 + Math.sin(t * Math.PI * 2) * 17 * (1 - t);
    const vertices = shape.vertices.map(view);
    const ground = height * .88;
    // A longer focal length keeps tetrahedral proportions and face labels stable.
    const distance = 9;
    const bottom = Math.max(...vertices.map(([, y, z]) => -y * scale * distance / (distance - z)));
    const cy = ground - bottom - bounce;
    const project = ([x, y, z]) => { const f = distance / (distance - z); return [cx + x * scale * f, cy - y * scale * f]; };
    function tangent([x, y, z], [dx, dy, dz]) {
        const denominator = distance - z;
        return [scale * distance * (dx * denominator + x * dz) / denominator ** 2,
            -scale * distance * (dy * denominator + y * dz) / denominator ** 2];
    }
    ctx.clearRect(0, 0, width, height);
    ctx.save(); ctx.translate(cx, ground + 2); ctx.scale(scale * (.9 - bounce / 100), scale * .2);
    const shadow = ctx.createRadialGradient(0, 0, 0, 0, 0, 1);
    shadow.addColorStop(0, `rgba(0,0,0,${.5 - bounce / 100})`); shadow.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = shadow; ctx.beginPath(); ctx.arc(0, 0, 1, 0, Math.PI * 2); ctx.fill(); ctx.restore();
    const light = unit([-.5, .8, 1.4]);
    const faces = shape.faces.map(face => ({ ...face, n: view(face.normal), c: view(face.center) }))
        .filter(face => dot(face.n, sub([0, 0, distance], face.c)) > 0).sort((a, b) => a.c[2] - b.c[2]);
    for (const face of faces) {
        const points = face.indices.map(i => project(vertices[i]));
        const lit = .42 + .58 * Math.max(0, dot(face.n, light));
        const rgb = colors[sides].map(channel => Math.round(channel * lit));
        ctx.beginPath(); points.forEach(([x, y], i) => i ? ctx.lineTo(x, y) : ctx.moveTo(x, y)); ctx.closePath();
        const gradient = ctx.createLinearGradient(0, cy - scale, width, cy + scale);
        gradient.addColorStop(0, `rgb(${rgb.map(c => Math.min(255, c + 14)).join(',')})`);
        gradient.addColorStop(1, `rgb(${rgb.join(',')})`);
        ctx.fillStyle = gradient; ctx.fill();
        ctx.strokeStyle = sides === 2 ? 'rgba(106,67,16,.7)' : 'rgba(20,22,30,.55)';
        ctx.lineWidth = sides === 2 && !face.label ? .65 : 1.25; ctx.stroke();
        if (!face.label || face.n[2] < .12) continue;
        ctx.save(); ctx.clip();
        for (const label of faceLabels(shape, face, sides, value)) {
            const c = view(label.position);
            const p = project(c);
            const labelBasis = label.basis || face.basis;
            const u = tangent(c, view(labelBasis[0]));
            const v = tangent(c, view(labelBasis[1]));
            ctx.save(); ctx.transform(u[0], u[1], -v[0], -v[1], p[0], p[1]);
            if (sides === 2) {
                ctx.strokeStyle = 'rgba(88,51,9,.65)'; ctx.lineWidth = .025;
                ctx.beginPath(); ctx.arc(0, 0, .83, 0, Math.PI * 2); ctx.stroke();
                ctx.beginPath(); ctx.arc(0, 0, .73, 0, Math.PI * 2); ctx.stroke();
            }
            // Fit the actual ink bounds inside the face's inscribed safe circle.
            ctx.font = '700 100px Arial, sans-serif';
            ctx.textAlign = 'left'; ctx.textBaseline = 'alphabetic';
            const metrics = ctx.measureText(label.text);
            const left = metrics.actualBoundingBoxLeft || 0;
            const right = metrics.actualBoundingBoxRight || metrics.width;
            const ascent = metrics.actualBoundingBoxAscent || 75;
            const descent = metrics.actualBoundingBoxDescent || 0;
            const reference = ctx.measureText(sides === 2 ? label.text : '8'.repeat(String(sides).length));
            const referenceDiagonal = Math.hypot(reference.width, (reference.actualBoundingBoxAscent || 75) + (reference.actualBoundingBoxDescent || 0));
            const factor = label.radius * 1.25 / Math.max(referenceDiagonal, Math.hypot(left + right, ascent + descent));
            const size = 100 * factor;
            const x = -(right - left) * factor / 2;
            const y = (ascent - descent) * factor / 2;
            ctx.font = `700 ${size}px Arial, sans-serif`;
            const highlight = label.selected && t > .92;
            ctx.fillStyle = highlight ? '#ffffff' : '#1e252c';
            if (highlight) { ctx.strokeStyle = '#1e252cb0'; ctx.lineWidth = size * .075; ctx.strokeText(label.text, x, y); }
            ctx.fillText(label.text, x, y);
            ctx.restore();
        }
        ctx.restore();
    }
}

export function showDie(host, sides, value, { reduced = false, finish = () => {} } = {}) {
    const canvas = host.ownerDocument.createElement('canvas');
    canvas.className = 'quick-dice-scene';
    canvas.setAttribute('aria-hidden', 'true');
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = 220 * ratio; canvas.height = 200 * ratio;
    host.prepend(canvas);
    const ctx = canvas.getContext('2d');
    if (!ctx) { canvas.remove(); finish(); return () => {}; }
    ctx.scale(ratio, ratio);
    let frame;
    let stopped = false;
    let start;
    const duration = sides === 2 ? 1200 : 1100;
    if (reduced) { paintDie(ctx, 220, 200, sides, value); finish(); return () => {}; }
    function draw(now) {
        if (stopped) return;
        start ??= now;
        const progress = Math.min(1, (now - start) / duration);
        paintDie(ctx, 220, 200, sides, value, progress);
        if (progress < 1) frame = requestAnimationFrame(draw);
        else finish();
    }
    frame = requestAnimationFrame(draw);
    return () => { stopped = true; cancelAnimationFrame(frame); };
}
