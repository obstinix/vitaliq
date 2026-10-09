/*
 * Data-driven charts (D3 subset). Each chart reads its data from an inert
 * <script type="application/json"> block rendered by the server, so the
 * chart and its fallback table always show the same numbers.
 */

const d3 = window.d3;
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

function readData(id) {
  const el = document.getElementById(id);
  if (!el) return [];
  try { return JSON.parse(el.textContent || '[]'); } catch (_) { return []; }
}

function compact(n) {
  const abs = Math.abs(n);
  if (abs >= 1e6) return (n / 1e6).toFixed(abs >= 1e7 ? 0 : 1).replace(/\.0$/, '') + 'M';
  if (abs >= 1e3) return (n / 1e3).toFixed(abs >= 1e4 ? 0 : 1).replace(/\.0$/, '') + 'k';
  return String(n);
}

function tooltip(container) {
  let tip = container.querySelector('.chart-tooltip');
  if (!tip) {
    tip = document.createElement('div');
    tip.className = 'chart-tooltip';
    tip.hidden = true;
    container.append(tip);
  }
  return tip;
}

function showTip(tip, x, y, lines) {
  tip.replaceChildren(...lines.map((line, i) => {
    const el = document.createElement(i === 0 ? 'strong' : 'div');
    el.textContent = line;
    return el;
  }));
  tip.style.left = x + 'px';
  tip.style.top = y + 'px';
  tip.hidden = false;
}

/* Daily line chart: submitted vs released. */
function drawDaily(container, data) {
  const width = Math.max(280, container.clientWidth);
  const height = Math.round(Math.min(300, Math.max(200, width * 0.45)));
  const m = { top: 12, right: 12, bottom: 28, left: 34 };
  const parse = d3.timeParse('%Y-%m-%d');
  const rows = data.map((d) => ({ date: parse(d.date), submitted: +d.submitted, released: +d.released }));
  const yMax = Math.max(4, d3.max(rows, (d) => Math.max(d.submitted, d.released)) || 0);

  const x = d3.scaleTime().domain(d3.extent(rows, (d) => d.date)).range([m.left, width - m.right]);
  const y = d3.scaleLinear().domain([0, yMax]).nice().range([height - m.bottom, m.top]);

  container.querySelector('svg')?.remove();
  const svg = d3.select(container).append('svg')
    .attr('viewBox', `0 0 ${width} ${height}`)
    .attr('aria-hidden', 'true')
    .attr('focusable', 'false');

  svg.append('g').attr('class', 'grid')
    .attr('transform', `translate(${m.left},0)`)
    .call(d3.axisLeft(y).ticks(4).tickSize(-(width - m.left - m.right)).tickFormat(''));
  svg.append('g').attr('class', 'axis')
    .attr('transform', `translate(0,${height - m.bottom})`)
    .call(d3.axisBottom(x).ticks(width < 500 ? 4 : 7).tickFormat(d3.timeFormat('%-d %b')).tickSizeOuter(0));
  svg.append('g').attr('class', 'axis')
    .attr('transform', `translate(${m.left},0)`)
    .call(d3.axisLeft(y).ticks(4).tickFormat((v) => (Number.isInteger(v) ? v : '')).tickSizeOuter(0));

  const green = css('--green');
  const coral = css('--coral');
  const area = d3.area().x((d) => x(d.date)).y0(y(0)).y1((d) => y(d.submitted)).curve(d3.curveMonotoneX);
  svg.append('path').datum(rows).attr('d', area).attr('fill', css('--green-wash'));

  const series = [['submitted', green, 2.25, null], ['released', coral, 2, '5 4']];
  series.forEach(([key, color, w, dash]) => {
    const line = d3.line().x((d) => x(d.date)).y((d) => y(d[key])).curve(d3.curveMonotoneX);
    const path = svg.append('path').datum(rows)
      .attr('d', line).attr('fill', 'none').attr('stroke', color).attr('stroke-width', w)
      .attr('stroke-linecap', 'round');
    if (dash) path.attr('stroke-dasharray', dash);
    if (!reduceMotion && !dash) {
      const length = path.node().getTotalLength();
      path.attr('stroke-dasharray', `${length} ${length}`).attr('stroke-dashoffset', length)
        .transition().duration(900).attr('stroke-dashoffset', 0)
        .on('end', function () { this.removeAttribute('stroke-dasharray'); });
    }
  });

  const tip = tooltip(container);
  const focus = svg.append('line').attr('stroke', css('--ink-3')).attr('stroke-dasharray', '2 3')
    .attr('y1', m.top).attr('y2', height - m.bottom).style('opacity', 0);
  svg.append('rect')
    .attr('x', m.left).attr('y', m.top).attr('width', width - m.left - m.right).attr('height', height - m.top - m.bottom)
    .attr('fill', 'transparent')
    .on('pointermove', (event) => {
      const [px] = pointer(event, svg.node(), width);
      const date = x.invert(px);
      let nearest = rows[0];
      rows.forEach((r) => { if (Math.abs(r.date - date) < Math.abs(nearest.date - date)) nearest = r; });
      const cx = x(nearest.date);
      focus.attr('x1', cx).attr('x2', cx).style('opacity', 1);
      const scale = container.clientWidth / width;
      showTip(tip, cx * scale, y(Math.max(nearest.submitted, nearest.released)) * scale, [
        d3.timeFormat('%a %-d %b')(nearest.date),
        `Submitted: ${nearest.submitted}`,
        `Released: ${nearest.released}`,
      ]);
    })
    .on('pointerleave', () => { tip.hidden = true; focus.style('opacity', 0); });
}

/* Convert a pointer event to SVG user units (the SVG scales with its container). */
function pointer(event, svgNode, viewWidth) {
  const rect = svgNode.getBoundingClientRect();
  return [((event.clientX - rect.left) / rect.width) * viewWidth];
}

/* Monthly bar chart: WHO historical cases for India. */
function drawMonthly(container, data) {
  const width = Math.max(280, container.clientWidth);
  const height = Math.round(Math.min(320, Math.max(220, width * 0.38)));
  const m = { top: 16, right: 8, bottom: 28, left: 44 };
  const rows = data.map((d) => ({ month: d.month, cases: +d.new_cases }));
  const x = d3.scaleBand().domain(rows.map((d) => d.month)).range([m.left, width - m.right]).padding(0.18);
  const y = d3.scaleLinear().domain([0, d3.max(rows, (d) => d.cases) || 1]).nice().range([height - m.bottom, m.top]);

  container.querySelector('svg')?.remove();
  const svg = d3.select(container).append('svg')
    .attr('viewBox', `0 0 ${width} ${height}`)
    .attr('aria-hidden', 'true')
    .attr('focusable', 'false');

  svg.append('g').attr('class', 'grid')
    .attr('transform', `translate(${m.left},0)`)
    .call(d3.axisLeft(y).ticks(4).tickSize(-(width - m.left - m.right)).tickFormat(''));
  const years = rows.filter((d) => d.month.endsWith('-01')).map((d) => d.month);
  svg.append('g').attr('class', 'axis')
    .attr('transform', `translate(0,${height - m.bottom})`)
    .call(d3.axisBottom(x).tickValues(years).tickFormat((v) => v.slice(0, 4)).tickSizeOuter(0));
  svg.append('g').attr('class', 'axis')
    .attr('transform', `translate(${m.left},0)`)
    .call(d3.axisLeft(y).ticks(4).tickFormat(compact).tickSizeOuter(0));

  const peak = rows.reduce((a, b) => (b.cases > a.cases ? b : a), rows[0]);
  const teal = css('--teal');
  const coral = css('--coral');
  const bars = svg.append('g').selectAll('rect').data(rows).join('rect')
    .attr('x', (d) => x(d.month))
    .attr('width', x.bandwidth())
    .attr('fill', (d) => (d === peak ? coral : teal))
    .attr('y', reduceMotion ? (d) => y(d.cases) : y(0))
    .attr('height', reduceMotion ? (d) => y(0) - y(d.cases) : 0);
  if (!reduceMotion) {
    bars.transition().duration(700).delay((_, i) => i * 6)
      .attr('y', (d) => y(d.cases)).attr('height', (d) => y(0) - y(d.cases));
  }

  if (peak) {
    svg.append('text')
      .attr('x', Math.min(width - 150, x(peak.month) + x.bandwidth() + 6))
      .attr('y', y(peak.cases) + 10)
      .attr('class', 'axis')
      .attr('font-family', css('--font-mono'))
      .attr('font-size', 11)
      .attr('fill', css('--coral-text'))
      .text(`Peak: ${d3.timeFormat('%b %Y')(d3.timeParse('%Y-%m')(peak.month))}`);
  }

  const tip = tooltip(container);
  const fmt = new Intl.NumberFormat('en-US');
  bars.on('pointerenter', (event, d) => {
    const scale = container.clientWidth / width;
    showTip(tip, (x(d.month) + x.bandwidth() / 2) * scale, y(d.cases) * scale, [
      d3.timeFormat('%B %Y')(d3.timeParse('%Y-%m')(d.month)),
      `${fmt.format(d.cases)} cases`,
    ]);
  }).on('pointerleave', () => { tip.hidden = true; });
}

const DRAW = { daily: drawDaily, monthly: drawMonthly };

function renderAll() {
  if (!d3) return;
  document.querySelectorAll('[data-chart]').forEach((container) => {
    const draw = DRAW[container.dataset.chart];
    const data = readData(container.dataset.source);
    if (draw && data.length) draw(container, data);
  });
}

if (d3) {
  renderAll();
  let pending = null;
  let lastWidth = window.innerWidth;
  window.addEventListener('resize', () => {
    if (window.innerWidth === lastWidth) return;
    lastWidth = window.innerWidth;
    clearTimeout(pending);
    pending = setTimeout(renderAll, 200);
  });
}
