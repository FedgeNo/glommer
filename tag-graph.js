/**
 * The /tags/ 3D force-directed hashtag graph (mirrors HashtagGraph.php).
 *
 * Two decoupled layers, per the classic split: a force simulation lays the tags
 * out in 3D MODEL space (repulsion between all nodes, attraction along the
 * co-occurrence edges - tighter the more posts two tags share, gravity to keep
 * it centred), and a separate view QUATERNION rotates that settled structure as
 * a rigid body for drawing. The physics never fights a drag because its forces
 * only depend on distances, which rotation leaves unchanged.
 *
 * The layout is settled once, synchronously, on load - so the graph appears
 * already laid out and completely still. It moves ONLY while being dragged
 * (no auto-spin, no inertia), so there's no animation loop at all: each
 * pointermove rotates the quaternion and repaints once.
 *
 * Nodes are the server-rendered HashtagNode links (still clickable); edges are
 * drawn on a <canvas> underlay (a rendering surface, not app "things", so no
 * per-line DOM). Sizes come from each tag's post count.
 */

const GRAPH_SELECTOR = '.HashtagGraph';

// --- quaternion helpers (x, y, z, w) ---------------------------------------

function quat_multiply(a, b) {
    const ax = a[0], ay = a[1], az = a[2], aw = a[3];
    const bx = b[0], by = b[1], bz = b[2], bw = b[3];

    return [
        aw * bx + ax * bw + ay * bz - az * by,
        aw * by - ax * bz + ay * bw + az * bx,
        aw * bz + ax * by - ay * bx + az * bw,
        aw * bw - ax * bx - ay * by - az * bz,
    ];
}

function quat_normalize(q) {
    const length = Math.hypot(q[0], q[1], q[2], q[3]) || 1;

    return [q[0] / length, q[1] / length, q[2] / length, q[3] / length];
}

// Row-major 3x3 rotation matrix for a unit quaternion.
function quat_to_matrix(q) {
    const x = q[0], y = q[1], z = q[2], w = q[3];
    const xx = x * x, yy = y * y, zz = z * z;
    const xy = x * y, xz = x * z, yz = y * z;
    const wx = w * x, wy = w * y, wz = w * z;

    return [
        1 - 2 * (yy + zz), 2 * (xy - wz), 2 * (xz + wy),
        2 * (xy + wz), 1 - 2 * (xx + zz), 2 * (yz - wx),
        2 * (xz - wy), 2 * (yz + wx), 1 - 2 * (xx + yy),
    ];
}

class HashtagGraph {
    // Layout / physics tuning.
    static MAX_ITERATIONS = 320;
    static GRAVITY = 0.03;
    static COOL = 0.986;

    // Interaction.
    static RADIANS_PER_PIXEL = 0.006;
    static DRAG_THRESHOLD = 5;

    constructor(element) {
        this.element = element;
        this.nodeElements = Array.from(element.querySelectorAll('.HashtagNode'));
        this.count = this.nodeElements.length;

        let edges = [];

        try {
            edges = JSON.parse(element.dataset.edges || '[]');
        } catch (error) {
            edges = [];
        }

        // View rotation and the current drag.
        this.orientation = [0, 0, 0, 1];
        this.dragging = false;
        this.suppressClick = false;

        this.sizeNodes();
        element.classList.add('Active');

        this.canvas = document.createElement('canvas');
        this.canvas.className = 'HashtagGraphEdges';
        this.canvas.setAttribute('aria-hidden', 'true');
        this.context = this.canvas.getContext('2d');
        element.insertBefore(this.canvas, element.firstChild);

        this.buildEdges(edges);
        this.measure();
        this.seed();
        // Settle the whole layout up front (a few hundred cheap iterations), so
        // it appears already laid out and dead still - it only ever moves when
        // dragged, never on its own.
        this.settle();
        this.render();

        window.addEventListener('resize', () => this.onResize());
    }

    // Font-size (and so node size) scales with the log of the post count, so one
    // runaway tag can't dwarf the rest.
    sizeNodes() {
        const logs = this.nodeElements.map((node) => Math.log(1 + Number(node.dataset.count || 1)));
        const min = Math.min(...logs);
        const max = Math.max(...logs);
        const span = max - min || 1;

        this.nodeElements.forEach((node, index) => {
            const normalized = (logs[index] - min) / span;
            node.style.fontSize = (0.82 + normalized * 1.15).toFixed(3) + 'rem';
            node.draggable = false;
            node.style.position = 'absolute';
        });
    }

    buildEdges(edges) {
        const weights = edges.map((edge) => edge.weight);
        const maxLog = Math.log(1 + Math.max(1, ...weights));

        this.edges = edges
            .filter((edge) => edge.a < this.count && edge.b < this.count)
            .map((edge) => {
                const strength = Math.log(1 + edge.weight) / (maxLog || 1);

                return {
                    a: edge.a,
                    b: edge.b,
                    // More shared posts -> stronger pull.
                    attraction: 1 + strength * 3,
                    lineWidth: 0.6 + strength * 2.2,
                };
            });
    }

    measure() {
        const rect = this.element.getBoundingClientRect();
        this.width = rect.width;
        this.height = rect.height;
        this.radius = Math.max(60, Math.min(this.width, this.height) * 0.34);
        this.ideal = 1.1 * this.radius / Math.cbrt(Math.max(2, this.count));

        // Node collision radius from its rendered box.
        this.nodeRadius = this.nodeElements.map((node) => Math.max(node.offsetWidth, node.offsetHeight) / 2 + 2);

        const ratio = window.devicePixelRatio || 1;
        this.canvas.width = Math.round(this.width * ratio);
        this.canvas.height = Math.round(this.height * ratio);
        this.context.setTransform(ratio, 0, 0, ratio, 0, 0);

        const edgeColor = getComputedStyle(this.element).getPropertyValue('--HashtagEdge').trim();
        this.edgeColor = edgeColor || 'rgba(120, 130, 125, 0.5)';

        // Keep the settled layout fitted to the (possibly resized) box.
        if (this.maxExtent) {
            this.computeFit();
        }
    }

    // Even, deterministic starting spread on a sphere (a Fibonacci lattice) -
    // never all at the origin, which would divide by zero into NaN.
    seed() {
        const count = this.count;
        this.position = new Float64Array(count * 3);
        this.displacement = new Float64Array(count * 3);
        const golden = Math.PI * (3 - Math.sqrt(5));
        const start = this.radius * 0.5;

        for (let i = 0; i < count; i++) {
            const y = count === 1 ? 0 : 1 - (i / (count - 1)) * 2;
            const ring = Math.sqrt(Math.max(0, 1 - y * y));
            const theta = golden * i;
            this.position[i * 3] = Math.cos(theta) * ring * start;
            this.position[i * 3 + 1] = y * start;
            this.position[i * 3 + 2] = Math.sin(theta) * ring * start;
        }

        this.temperature = this.radius * 0.16;
    }

    // Run the whole simulation to rest right now (cheap at these sizes), then
    // work out how much to scale it so it fits the viewport.
    settle() {
        for (let i = 0; i < HashtagGraph.MAX_ITERATIONS; i++) {
            this.stepPhysics();
        }

        this.computeFit();
    }

    // The furthest node from the centre sets a scale that keeps the whole graph
    // inside the box, whatever the physics settled to.
    computeFit() {
        let maxSquared = 1;

        for (let i = 0; i < this.count; i++) {
            const x = this.position[i * 3];
            const y = this.position[i * 3 + 1];
            const z = this.position[i * 3 + 2];
            const squared = x * x + y * y + z * z;

            if (squared > maxSquared) {
                maxSquared = squared;
            }
        }

        this.maxExtent = Math.sqrt(maxSquared);
        this.fitScale = Math.min(2.5, (this.radius * 0.85) / this.maxExtent);
    }

    // One Fruchterman-Reingold-style step: repel every pair, attract along edges,
    // pull toward the centre, then move each node by at most the current
    // "temperature" (which cools each step) so the layout can never explode.
    stepPhysics() {
        const position = this.position;
        const displacement = this.displacement;
        const count = this.count;
        const k = this.ideal;
        displacement.fill(0);

        for (let i = 0; i < count; i++) {
            for (let j = i + 1; j < count; j++) {
                const dx = position[i * 3] - position[j * 3];
                const dy = position[i * 3 + 1] - position[j * 3 + 1];
                const dz = position[i * 3 + 2] - position[j * 3 + 2];
                const distance = Math.sqrt(dx * dx + dy * dy + dz * dz) || 0.01;

                let force = (k * k) / distance;

                // Keep big nodes from overlapping their neighbours.
                const minimum = this.nodeRadius[i] + this.nodeRadius[j] + 4;
                if (distance < minimum) {
                    force += (minimum - distance) * 0.9;
                }

                const fx = (dx / distance) * force;
                const fy = (dy / distance) * force;
                const fz = (dz / distance) * force;

                displacement[i * 3] += fx;
                displacement[i * 3 + 1] += fy;
                displacement[i * 3 + 2] += fz;
                displacement[j * 3] -= fx;
                displacement[j * 3 + 1] -= fy;
                displacement[j * 3 + 2] -= fz;
            }
        }

        for (const edge of this.edges) {
            const a = edge.a, b = edge.b;
            const dx = position[a * 3] - position[b * 3];
            const dy = position[a * 3 + 1] - position[b * 3 + 1];
            const dz = position[a * 3 + 2] - position[b * 3 + 2];
            const distance = Math.sqrt(dx * dx + dy * dy + dz * dz) || 0.01;

            const force = ((distance * distance) / k) * edge.attraction;
            const fx = (dx / distance) * force;
            const fy = (dy / distance) * force;
            const fz = (dz / distance) * force;

            displacement[a * 3] -= fx;
            displacement[a * 3 + 1] -= fy;
            displacement[a * 3 + 2] -= fz;
            displacement[b * 3] += fx;
            displacement[b * 3 + 1] += fy;
            displacement[b * 3 + 2] += fz;
        }

        const temperature = this.temperature;
        let centerX = 0, centerY = 0, centerZ = 0;

        for (let i = 0; i < count; i++) {
            displacement[i * 3] -= position[i * 3] * HashtagGraph.GRAVITY;
            displacement[i * 3 + 1] -= position[i * 3 + 1] * HashtagGraph.GRAVITY;
            displacement[i * 3 + 2] -= position[i * 3 + 2] * HashtagGraph.GRAVITY;

            const dx = displacement[i * 3];
            const dy = displacement[i * 3 + 1];
            const dz = displacement[i * 3 + 2];
            const length = Math.sqrt(dx * dx + dy * dy + dz * dz) || 0.01;
            const limited = Math.min(length, temperature) / length;

            position[i * 3] += dx * limited;
            position[i * 3 + 1] += dy * limited;
            position[i * 3 + 2] += dz * limited;

            centerX += position[i * 3];
            centerY += position[i * 3 + 1];
            centerZ += position[i * 3 + 2];
        }

        // Pin the centroid at the origin so the ball spins in place, not orbits.
        centerX /= count;
        centerY /= count;
        centerZ /= count;

        for (let i = 0; i < count; i++) {
            position[i * 3] -= centerX;
            position[i * 3 + 1] -= centerY;
            position[i * 3 + 2] -= centerZ;
        }

        this.temperature = Math.max(this.radius * 0.006, temperature * HashtagGraph.COOL);
    }

    // Turn a screen-space drag delta into an incremental rotation and premultiply
    // it onto the orientation - premultiplying keeps it screen-relative, so
    // "drag right" always spins right no matter how the graph is already turned.
    applyScreenDelta(dx, dy) {
        const distance = Math.hypot(dx, dy);
        if (distance < 1e-4) {
            return;
        }

        const angle = distance * HashtagGraph.RADIANS_PER_PIXEL;
        const scale = Math.sin(angle / 2) / distance;
        const delta = [-dy * scale, dx * scale, 0, Math.cos(angle / 2)];

        this.orientation = quat_normalize(quat_multiply(delta, this.orientation));
    }

    render() {
        const matrix = quat_to_matrix(this.orientation);
        const centerX = this.width / 2;
        const centerY = this.height / 2;
        const extent = this.maxExtent || 1;
        const fit = this.fitScale || 1;
        const position = this.position;
        const projected = [];

        for (let i = 0; i < this.count; i++) {
            const x = position[i * 3], y = position[i * 3 + 1], z = position[i * 3 + 2];
            const rx = (matrix[0] * x + matrix[1] * y + matrix[2] * z) * fit;
            const ry = (matrix[3] * x + matrix[4] * y + matrix[5] * z) * fit;
            const rz = matrix[6] * x + matrix[7] * y + matrix[8] * z;

            const depth = Math.max(0, Math.min(1, (rz + extent) / (2 * extent)));
            const scale = 0.62 + depth * 0.58;

            projected.push({ x: centerX + rx, y: centerY + ry, depth });

            const node = this.nodeElements[i];
            node.style.transform =
                'translate(-50%, -50%) translate3d(' + (centerX + rx).toFixed(1) + 'px, ' + (centerY + ry).toFixed(1) + 'px, 0) scale(' + scale.toFixed(3) + ')';
            node.style.opacity = (0.4 + depth * 0.6).toFixed(3);
            node.style.zIndex = String(Math.round(depth * 100));
        }

        this.drawEdges(projected);
    }

    drawEdges(projected) {
        const context = this.context;
        context.clearRect(0, 0, this.width, this.height);

        for (const edge of this.edges) {
            const a = projected[edge.a];
            const b = projected[edge.b];
            const depth = (a.depth + b.depth) / 2;

            context.globalAlpha = 0.12 + depth * 0.5;
            context.strokeStyle = this.edgeColor;
            context.lineWidth = edge.lineWidth * (0.7 + depth * 0.6);
            context.beginPath();
            context.moveTo(a.x, a.y);
            context.lineTo(b.x, b.y);
            context.stroke();
        }

        context.globalAlpha = 1;
    }

    onResize() {
        this.measure();
        this.render();
    }

    // --- drag handling (driven by the delegated document listeners) ---------

    onDown(event) {
        this.dragging = true;
        this.suppressClick = false;
        this.startX = event.clientX;
        this.startY = event.clientY;
        this.lastX = event.clientX;
        this.lastY = event.clientY;
        this.moved = false;

        if (this.element.setPointerCapture) {
            try {
                this.element.setPointerCapture(event.pointerId);
            } catch (error) {
                // A stale pointer id just means no capture; dragging still works.
            }
        }
    }

    onMove(event) {
        if (!this.dragging) {
            return;
        }

        const dx = event.clientX - this.lastX;
        const dy = event.clientY - this.lastY;
        this.lastX = event.clientX;
        this.lastY = event.clientY;

        if (!this.moved && Math.hypot(event.clientX - this.startX, event.clientY - this.startY) > HashtagGraph.DRAG_THRESHOLD) {
            this.moved = true;
        }

        if (this.moved) {
            this.suppressClick = true;
            this.applyScreenDelta(dx, dy);
            this.render();
        }
    }

    onUp() {
        this.dragging = false;
    }
}

// --- delegated interaction --------------------------------------------------

function graph_for(target) {
    const element = target.closest(GRAPH_SELECTOR + '.Active');
    return element && element.__hashtagGraph ? element.__hashtagGraph : null;
}

let active_graph = null;

document.addEventListener('pointerdown', (event) => {
    const graph = graph_for(event.target);
    if (!graph) {
        return;
    }

    // Stop the browser's own text-selection / link-image drag from hijacking it.
    event.preventDefault();
    active_graph = graph;
    graph.onDown(event);
});

document.addEventListener('pointermove', (event) => {
    if (active_graph) {
        active_graph.onMove(event);
    }
});

function end_drag(event) {
    if (active_graph) {
        active_graph.onUp(event);
        active_graph = null;
    }
}

document.addEventListener('pointerup', end_drag);
document.addEventListener('pointercancel', end_drag);

// A drag that started on a tag must not also follow its link on release.
document.addEventListener('click', (event) => {
    const node = event.target.closest('.HashtagNode');
    if (!node) {
        return;
    }

    const element = node.closest(GRAPH_SELECTOR);
    if (element && element.__hashtagGraph && element.__hashtagGraph.suppressClick) {
        event.preventDefault();
        element.__hashtagGraph.suppressClick = false;
    }
});

function init_tag_graphs(root) {
    (root || document).querySelectorAll(GRAPH_SELECTOR).forEach((element) => {
        if (!element.__hashtagGraph) {
            element.__hashtagGraph = new HashtagGraph(element);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => init_tag_graphs());
