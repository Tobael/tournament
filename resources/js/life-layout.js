// Clockwise seating, using the long edges of a phone as the sides of the table.
export function lifeLayout(count, width, height) {
    const layouts = {
        1: [[0, 0, 1, 1, 0]],
        2: [[0, 0, 1, .5, 180], [0, .5, 1, .5, 0]],
        3: [[0, 0, 1, .5, 180], [.5, .5, .5, .5, -90], [0, .5, .5, .5, 90]],
        4: [[0, 0, .5, .5, 90], [.5, 0, .5, .5, -90], [.5, .5, .5, .5, -90], [0, .5, .5, .5, 90]],
        5: [[0, 0, 1, .28, 180], [.5, .28, .5, .36, -90], [.5, .64, .5, .36, -90], [0, .64, .5, .36, 90], [0, .28, .5, .36, 90]],
    };
    return (layouts[count] || layouts[1]).map(([x, y, w, h, angle]) => {
        if (width > height) [x, y, w, h, angle] = [y, 1 - x - w, h, w, angle - 90];
        return { x, y, width: w, height: h, angle };
    });
}

export function setupLifeLayout(root) {
    const board = root.querySelector('.life-grid');
    const seats = [...board.querySelectorAll('[data-life-seat]')];
    const key = `life-seating.${root.dataset.invite}.${JSON.parse(root.dataset.state).matchId}`;
    let rotations = {};
    try { rotations = JSON.parse(localStorage.getItem(key)) || {}; } catch {}
    function layout() {
        const width = board.clientWidth;
        const height = board.clientHeight;
        if (!width || !height) return;
        const positions = lifeLayout(seats.length, width, height);
        seats.forEach((seat, index) => {
            const position = positions[index];
            const angle = position.angle + (Number(rotations[seat.dataset.lifeSeat]) || 0);
            const sideways = Math.abs(angle % 180) === 90;
            seat.style.left = `${position.x * 100}%`;
            seat.style.top = `${position.y * 100}%`;
            seat.style.width = `${position.width * 100}%`;
            seat.style.height = `${position.height * 100}%`;
            const paneWidth = Math.max(0, position.width * width - 8);
            const paneHeight = Math.max(0, position.height * height - 8);
            const content = seat.querySelector('.life-seat-content');
            content.style.width = `${sideways ? paneHeight : paneWidth}px`;
            content.style.height = `${sideways ? paneWidth : paneHeight}px`;
            content.style.transform = `translate(-50%, -50%) rotate(${angle}deg)`;
        });
    }
    const rotate = event => {
        const button = event.target.closest('[data-life-rotate]');
        if (!button) return;
        const id = button.closest('[data-life-seat]').dataset.lifeSeat;
        rotations[id] = ((Number(rotations[id]) || 0) + 90) % 360;
        try { localStorage.setItem(key, JSON.stringify(rotations)); } catch {}
        layout();
    };
    board.addEventListener('click', rotate);
    const observer = new ResizeObserver(layout);
    observer.observe(board);
    layout();
    return () => { observer.disconnect(); board.removeEventListener('click', rotate); };
}
