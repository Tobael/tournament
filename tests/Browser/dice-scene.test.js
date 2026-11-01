import test from 'node:test';
import assert from 'node:assert/strict';
import { createDieMesh, diePose, faceLabels } from '../../resources/js/dice-scene.js';

for (const sides of [2, 4, 6, 8, 10, 12, 20]) {
    test(`d${sides} is a closed solid with planar faces and one label per result`, () => {
        const mesh = createDieMesh(sides);
        assert.deepEqual(mesh.faces.filter(f => f.label).map(f => f.label).sort((a,b) => a-b), Array.from({ length: sides }, (_, i) => i + 1));
        const edges = new Map();
        for (const face of mesh.faces) {
            const distance = face.center.reduce((sum, c, i) => sum + c * face.normal[i], 0);
            assert.ok(distance > 0);
            for (const index of face.indices) {
                const point = mesh.vertices[index];
                assert.ok(Math.abs(point.reduce((sum, c, i) => sum + c * face.normal[i], 0) - distance) < 1e-8);
            }
            face.indices.forEach((a, i) => {
                const b = face.indices[(i + 1) % face.indices.length];
                const key = [a, b].sort((x,y) => x-y).join(':');
                edges.set(key, (edges.get(key) || 0) + 1);
            });
        }
        assert.ok([...edges.values()].every(count => count === 2));
        assert.equal(mesh.vertices.length - edges.size + mesh.faces.length, 2);
    });
}


for (const sides of [2, 4, 6, 8, 10, 12, 20]) {
    test(`every d${sides} result lands uppermost and only its own labels are selected`, () => {
        for (let result = 1; result <= sides; result++) {
            const pose = diePose(sides, result);
            const world = pose.shape.vertices.map(pose.world);
            const top = Math.max(...world.map(point => point[1]));
            if (sides === 4) {
                assert.ok(Math.abs(world[result - 1][1] - top) < 1e-8);
                const base = world.filter((_, index) => index !== result - 1);
                assert.ok(Math.max(...base.map(point => point[1])) - Math.min(...base.map(point => point[1])) < 1e-8);
            } else {
                assert.ok(pose.world(pose.selected.normal)[1] > .999999);
                for (const index of pose.selected.indices) assert.ok(Math.abs(world[index][1] - top) < 1e-8);
            }
            const selected = pose.shape.faces.flatMap(face => faceLabels(pose.shape, face, sides, result)).filter(label => label.selected);
            assert.ok(selected.length > 0);
            assert.ok(selected.every(label => label.text === (sides === 2 ? (result === 1 ? 'K' : 'Z') : String(result))));
        }
    });
}

test('face labels have a safe radius inside their face and a regular d4 keeps six equal edges', () => {
    for (const sides of [2, 4, 6, 8, 10, 12, 20]) {
        const shape = createDieMesh(sides);
        for (const face of shape.faces) for (const label of faceLabels(shape, face, sides, 1)) {
            assert.ok(Number.isFinite(label.radius) && label.radius > 0);
            const planeDistance = label.position.reduce((sum, value, i) => sum + (value - face.center[i]) * face.normal[i], 0);
            assert.ok(Math.abs(planeDistance) < 1e-8);
            if (sides !== 4) assert.deepEqual(label.position, face.center);
        }
    }
    const vertices = createDieMesh(4).vertices;
    const lengths = [];
    for (let a = 0; a < 4; a++) for (let b = a + 1; b < 4; b++) lengths.push(Math.hypot(...vertices[a].map((v, i) => v - vertices[b][i])));
    assert.ok(Math.max(...lengths) - Math.min(...lengths) < 1e-8);
});
