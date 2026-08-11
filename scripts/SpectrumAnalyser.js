import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * The moving spectrum above an audio player.
 *
 * Taps the graph the browser is already decoding for playback rather than
 * decoding the file itself, so it costs one FFT window of memory and starts
 * instantly. It shows the present instant and nothing ahead of the playhead,
 * because nothing ahead of the playhead has been decoded.
 */
export class SpectrumAnalyser {
    /** 1024 samples in, 512 bins out - enough shape to read, cheap to draw. */
    static FFT_SIZE = 1024;

    /** How many bars the bins are grouped into. */
    static BARS = 48;

    /**
     * How much of the spectrum to draw. The top of it is empty on nearly all
     * recorded sound, and drawing it wastes most of the width on a flat line.
     */
    static USED_BINS = 0.55;

    /**
     * Barely any smoothing in the analyser itself. Its own smoothing is
     * symmetric - it slows the fall as much as the rise - so held loud sound
     * pins every bar to the ceiling and the display stops saying anything.
     * The fall is shaped below instead.
     */
    static SMOOTHING = 0.2;

    /**
     * How much of its height a bar keeps each frame while the sound under it
     * is quieter than it was. Rises are taken immediately, so a bar snaps up
     * and drops away - which is what makes a sustained peak still read as one
     * rather than as a flat line.
     */
    static DECAY = 0.78;

    /**
     * One graph per audio element, because createMediaElementSource may only
     * be called once for an element - a second call throws, and the element is
     * left silent.
     */
    static #graphs = new WeakMap();

    static init() {
        // play does not bubble, so this listens on the way down instead. It is
        // also the user gesture an AudioContext needs to start.
        document.addEventListener('play', (event) => {
            const audio = event.target;

            if (!(audio instanceof HTMLMediaElement) || !audio.classList.contains('Audio')) return;

            const canvas = audio.parentElement?.querySelector('.SpectrumAnalyser');

            if (!canvas) return;

            // Somebody who has asked for less movement gets the player and no
            // dancing bars.
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            SpectrumAnalyser.#start(audio, canvas);
        }, true);
    }

    static #graphFor(audio) {
        const existing = SpectrumAnalyser.#graphs.get(audio);

        if (existing) return existing;

        const AudioContextClass = window.AudioContext || window.webkitAudioContext;

        if (!AudioContextClass) return null;

        const context = new AudioContextClass();
        const analyser = context.createAnalyser();
        analyser.fftSize = SpectrumAnalyser.FFT_SIZE;
        analyser.smoothingTimeConstant = SpectrumAnalyser.SMOOTHING;

        // Through the analyser AND on to the speakers: a source routed into a
        // node that goes nowhere plays silently.
        context.createMediaElementSource(audio).connect(analyser);
        analyser.connect(context.destination);

        const graph = {
            context,
            analyser,
            bins: new Uint8Array(analyser.frequencyBinCount),
            // What each bar is showing, carried between frames so the fall can
            // be shaped. Rises replace it outright.
            heights: new Float32Array(SpectrumAnalyser.BARS),
            drawing: false,
        };
        SpectrumAnalyser.#graphs.set(audio, graph);

        return graph;
    }

    static #start(audio, canvas) {
        const graph = SpectrumAnalyser.#graphFor(audio);

        if (!graph || graph.drawing) return;

        // A context created before the gesture starts suspended; a second play
        // after a pause finds it suspended again.
        if (graph.context.state === 'suspended') graph.context.resume();

        graph.drawing = true;

        const surface = canvas.getContext('2d');
        const ratio = window.devicePixelRatio || 1;

        // Drawn at the display's own resolution, so the bars have hard edges
        // rather than being scaled up from a smaller buffer.
        canvas.width = canvas.clientWidth * ratio || canvas.width;
        canvas.height = canvas.clientHeight * ratio || canvas.height;

        const colour = getComputedStyle(canvas).color;

        const draw = () => {
            if (audio.paused || audio.ended) {
                graph.drawing = false;
                surface.clearRect(0, 0, canvas.width, canvas.height);

                return;
            }

            graph.analyser.getByteFrequencyData(graph.bins);
            surface.clearRect(0, 0, canvas.width, canvas.height);
            surface.fillStyle = colour;

            const used = Math.floor(graph.bins.length * SpectrumAnalyser.USED_BINS);
            const perBar = Math.max(1, Math.floor(used / SpectrumAnalyser.BARS));
            const barWidth = canvas.width / SpectrumAnalyser.BARS;

            for (let bar = 0; bar < SpectrumAnalyser.BARS; bar++) {
                let total = 0;

                for (let offset = 0; offset < perBar; offset++) {
                    total += graph.bins[bar * perBar + offset] || 0;
                }

                const level = total / perBar / 255;

                // Straight up, gently down.
                graph.heights[bar] = level > graph.heights[bar]
                    ? level
                    : graph.heights[bar] * SpectrumAnalyser.DECAY;

                const height = graph.heights[bar] * canvas.height;

                surface.fillRect(
                    bar * barWidth,
                    canvas.height - height,
                    Math.max(1, barWidth - ratio),
                    height
                );
            }

            requestAnimationFrame(draw);
        };

        requestAnimationFrame(draw);
    }
}

ReadyHandler.add(SpectrumAnalyser.init);
