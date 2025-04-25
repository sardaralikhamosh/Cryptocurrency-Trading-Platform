<!-- slider.blade.php -->
<div class="chart-slider-container" style="width: 100%; height: 500px; position: relative; overflow: hidden; background-color: #0f172a; border-radius: 8px;">
    <div id="trading-chart-container" style="width: 100%; height: 100%;"></div>
    
    <!-- Navigation arrows -->
    <div style="position: absolute; top: 50%; left: 10px; transform: translateY(-50%); background: rgba(0,0,0,0.5); border-radius: 50%; padding: 8px; cursor: pointer; z-index: 10;" onclick="prevChart()">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </div>
    <div style="position: absolute; top: 50%; right: 10px; transform: translateY(-50%); background: rgba(0,0,0,0.5); border-radius: 50%; padding: 8px; cursor: pointer; z-index: 10;" onclick="nextChart()">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
    </div>
    
    <!-- Chart info overlay -->
    <div id="chart-price-display" style="position: absolute; top: 20px; left: 20px; color: white; font-family: Arial, sans-serif; z-index: 5;">
        <div id="current-price" style="font-size: 28px; font-weight: bold;">$28,500.00</div>
        <div id="price-change" style="font-size: 14px; color: #4ade80;">▲ 2.35%</div>
    </div>
    
    <!-- Indicator dots -->
    <div style="position: absolute; bottom: 15px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 10;">
        <div class="chart-dot active" style="width: 10px; height: 10px; border-radius: 50%; background-color: white; cursor: pointer;" onclick="switchChart(0)"></div>
        <div class="chart-dot" style="width: 10px; height: 10px; border-radius: 50%; background-color: rgba(255,255,255,0.5); cursor: pointer;" onclick="switchChart(1)"></div>
        <div class="chart-dot" style="width: 10px; height: 10px; border-radius: 50%; background-color: rgba(255,255,255,0.5); cursor: pointer;" onclick="switchChart(2)"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart configuration
    let currentChartIndex = 0;
    const chartTypes = ['BTC/USD', 'ETH/USD', 'SOL/USD'];
    const chartColors = ['#f59e0b', '#3b82f6', '#10b981'];
    
    // Chart state
    let nodes = [];
    let connections = [];
    let prices = [];
    let currentPrice = 28500;
    let animationFrame;
    let svg;
    
    // Initialize chart
    initChart();
    
    function initChart() {
        // Create SVG element
        const container = document.getElementById('trading-chart-container');
        container.innerHTML = ''; // Clear previous chart
        
        svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', '100%');
        svg.setAttribute('height', '100%');
        svg.setAttribute('viewBox', '0 0 800 400');
        container.appendChild(svg);
        
        // Add gradient for area under chart
        const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        svg.appendChild(defs);
        
        const gradient = document.createElementNS('http://www.w3.org/2000/svg', 'linearGradient');
        gradient.setAttribute('id', 'areaGradient');
        gradient.setAttribute('x1', '0');
        gradient.setAttribute('y1', '0');
        gradient.setAttribute('x2', '0');
        gradient.setAttribute('y2', '1');
        defs.appendChild(gradient);
        
        const stop1 = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
        stop1.setAttribute('offset', '0%');
        stop1.setAttribute('stop-color', `${chartColors[currentChartIndex]}33`);
        gradient.appendChild(stop1);
        
        const stop2 = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
        stop2.setAttribute('offset', '100%');
        stop2.setAttribute('stop-color', 'rgba(0, 0, 0, 0)');
        gradient.appendChild(stop2);
        
        // Create grid lines
        for (let i = 0; i < 5; i++) {
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', '0');
            line.setAttribute('y1', i * 100);
            line.setAttribute('x2', '800');
            line.setAttribute('y2', i * 100);
            line.setAttribute('stroke', '#2a3a55');
            line.setAttribute('stroke-width', '1');
            line.setAttribute('stroke-dasharray', '5,5');
            svg.appendChild(line);
        }
        
        for (let i = 0; i < 9; i++) {
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', i * 100);
            line.setAttribute('y1', '0');
            line.setAttribute('x2', i * 100);
            line.setAttribute('y2', '400');
            line.setAttribute('stroke', '#2a3a55');
            line.setAttribute('stroke-width', '1');
            line.setAttribute('stroke-dasharray', '5,5');
            svg.appendChild(line);
        }
        
        // Generate initial data
        generateData();
        
        // Start animation loop
        startAnimationLoop();
    }
    
    function generateData() {
        // Generate price data
        prices = [];
        currentPrice = 25000 + Math.random() * 10000;
        
        let price = currentPrice;
        for (let i = 0; i < 50; i++) {
            price = price + (Math.random() - 0.5) * 300;
            prices.push({
                price: price,
                timestamp: Date.now() - (50 - i) * 1000
            });
        }
        
        updatePriceDisplay();
        
        // Generate nodes
        nodes = [];
        for (let i = 0; i < 15; i++) {
            nodes.push({
                id: i,
                x: Math.random() * 800,
                y: Math.random() * 400,
                size: Math.random() * 10 + 5,
                speedX: (Math.random() - 0.5) * 1,
                speedY: (Math.random() - 0.5) * 1,
                color: i % 3 === 0 ? chartColors[currentChartIndex] : i % 2 === 0 ? '#3498db' : '#2ecc71'
            });
        }
        
        // Generate connections
        connections = [];
        for (let i = 0; i < nodes.length; i++) {
            const connectionsCount = Math.floor(Math.random() * 3) + 1;
            for (let j = 0; j < connectionsCount; j++) {
                const targetIndex = Math.floor(Math.random() * nodes.length);
                if (targetIndex !== i) {
                    connections.push({
                        source: i,
                        target: targetIndex,
                        strength: Math.random() * 0.8 + 0.2,
                        active: Math.random() > 0.5
                    });
                }
            }
        }
    }
    
    function updatePriceDisplay() {
        const priceElement = document.getElementById('current-price');
        const changeElement = document.getElementById('price-change');
        
        if (prices.length > 0) {
            const latestPrice = prices[prices.length - 1].price;
            const firstPrice = prices[0].price;
            const priceChange = ((latestPrice - firstPrice) / firstPrice) * 100;
            
            priceElement.textContent = '$' + latestPrice.toFixed(2);
            
            if (priceChange >= 0) {
                changeElement.textContent = `▲ ${priceChange.toFixed(2)}%`;
                changeElement.style.color = '#4ade80';
            } else {
                changeElement.textContent = `▼ ${Math.abs(priceChange).toFixed(2)}%`;
                changeElement.style.color = '#f87171';
            }
        }
    }
    
    function drawChart() {
        // Clear previous elements (except grid lines and defs)
        while (svg.childNodes.length > 19) { // 18 grid lines + defs
            svg.removeChild(svg.lastChild);
        }
        
        // Draw connections
        connections.forEach((connection) => {
            const source = nodes[connection.source];
            const target = nodes[connection.target];
            
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', source.x);
            line.setAttribute('y1', source.y);
            line.setAttribute('x2', target.x);
            line.setAttribute('y2', target.y);
            line.setAttribute('stroke', connection.active ? '#ffffff' : '#3a506b');
            line.setAttribute('stroke-width', connection.strength * 2);
            line.setAttribute('stroke-opacity', connection.active ? 0.7 : 0.2);
            svg.appendChild(line);
            
            // Add pulse for active connections
            if (connection.active && Math.random() > 0.7) {
                const midX = (source.x + target.x) / 2;
                const midY = (source.y + target.y) / 2;
                
                const pulse = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                pulse.setAttribute('cx', midX);
                pulse.setAttribute('cy', midY);
                pulse.setAttribute('r', '5');
                pulse.setAttribute('fill', 'none');
                pulse.setAttribute('stroke', '#ffffff');
                pulse.setAttribute('stroke-width', '2');
                pulse.setAttribute('opacity', '0.7');
                
                const animateR = document.createElementNS('http://www.w3.org/2000/svg', 'animate');
                animateR.setAttribute('attributeName', 'r');
                animateR.setAttribute('from', '5');
                animateR.setAttribute('to', '20');
                animateR.setAttribute('dur', '1s');
                animateR.setAttribute('begin', '0s');
                animateR.setAttribute('repeatCount', 'indefinite');
                pulse.appendChild(animateR);
                
                const animateO = document.createElementNS('http://www.w3.org/2000/svg', 'animate');
                animateO.setAttribute('attributeName', 'opacity');
                animateO.setAttribute('from', '0.7');
                animateO.setAttribute('to', '0');
                animateO.setAttribute('dur', '1s');
                animateO.setAttribute('begin', '0s');
                animateO.setAttribute('repeatCount', 'indefinite');
                pulse.appendChild(animateO);
                
                svg.appendChild(pulse);
            }
        });
        
        // Draw price chart path
        if (prices.length > 0) {
            const minPrice = Math.min(...prices.map(p => p.price)) - 100;
            const maxPrice = Math.max(...prices.map(p => p.price)) + 100;
            const priceRange = maxPrice - minPrice;
            
            const points = prices.map((point, index) => {
                const x = (index / (prices.length - 1)) * 800;
                const y = 400 - ((point.price - minPrice) / priceRange) * 400;
                return `${x},${y}`;
            });
            
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', `M ${points.join(" L ")}`);
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke', chartColors[currentChartIndex]);
            path.setAttribute('stroke-width', '3');
            svg.appendChild(path);
            
            // Draw area under chart
            const areaPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            areaPath.setAttribute('d', `${path.getAttribute('d')} L 800,400 L 0,400 Z`);
            areaPath.setAttribute('fill', 'url(#areaGradient)');
            svg.appendChild(areaPath);
            
            // Add current price indicator
            const lastPoint = points[points.length - 1].split(',');
            const indicator = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            indicator.setAttribute('cx', lastPoint[0]);
            indicator.setAttribute('cy', lastPoint[1]);
            indicator.setAttribute('r', '6');
            indicator.setAttribute('fill', chartColors[currentChartIndex]);
            indicator.setAttribute('stroke', '#ffffff');
            indicator.setAttribute('stroke-width', '2');
            svg.appendChild(indicator);
        }
        
        // Draw nodes
        nodes.forEach((node) => {
            const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            circle.setAttribute('cx', node.x);
            circle.setAttribute('cy', node.y);
            circle.setAttribute('r', node.size);
            circle.setAttribute('fill', node.color);
            circle.setAttribute('opacity', '0.8');
            svg.appendChild(circle);
            
            const ringCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            ringCircle.setAttribute('cx', node.x);
            ringCircle.setAttribute('cy', node.y);
            ringCircle.setAttribute('r', node.size + 3);
            ringCircle.setAttribute('fill', 'none');
            ringCircle.setAttribute('stroke', node.color);
            ringCircle.setAttribute('stroke-width', '1');
            ringCircle.setAttribute('opacity', '0.5');
            svg.appendChild(ringCircle);
        });
    }
    
    function updateNodes() {
        nodes.forEach((node) => {
            // Update position
            node.x += node.speedX;
            node.y += node.speedY;
            
            // Bounce off edges
            if (node.x < 0 || node.x > 800) {
                node.speedX *= -1;
                node.x += node.speedX;
            }
            if (node.y < 0 || node.y > 400) {
                node.speedY *= -1;
                node.y += node.speedY;
            }
        });
    }
    
    function updateConnections() {
        if (Math.random() > 0.85) {
            connections.forEach((connection) => {
                if (Math.random() > 0.7) {
                    connection.active = !connection.active;
                }
            });
        }
    }
    
    function updatePrices() {
        if (Math.random() > 0.5) {
            const lastPrice = prices[prices.length - 1].price;
            const newPrice = lastPrice + (Math.random() - 0.5) * 80;
            
            prices.shift();
            prices.push({
                price: newPrice,
                timestamp: Date.now()
            });
            
            updatePriceDisplay();
        }
    }
    
    function startAnimationLoop() {
        function animate() {
            updateNodes();
            updateConnections();
            updatePrices();
            drawChart();
            
            animationFrame = requestAnimationFrame(animate);
        }
        
        animate();
    }
    
    function stopAnimationLoop() {
        if (animationFrame) {
            cancelAnimationFrame(animationFrame);
        }
    }
    
    // Navigation functions (will be accessible globally)
    window.prevChart = function() {
        currentChartIndex = (currentChartIndex - 1 + chartTypes.length) % chartTypes.length;
        switchChart(currentChartIndex);
    };
    
    window.nextChart = function() {
        currentChartIndex = (currentChartIndex + 1) % chartTypes.length;
        switchChart(currentChartIndex);
    };
    
    window.switchChart = function(index) {
        if (index === currentChartIndex) return;
        
        // Update active dot indicator
        const dots = document.querySelectorAll('.chart-dot');
        dots.forEach((dot, i) => {
            if (i === index) {
                dot.classList.add('active');
                dot.style.backgroundColor = 'white';
            } else {
                dot.classList.remove('active');
                dot.style.backgroundColor = 'rgba(255,255,255,0.5)';
            }
        });
        
        // Stop current animation
        stopAnimationLoop();
        
        // Update chart index
        currentChartIndex = index;
        
        // Regenerate data and restart
        initChart();
    };
});
</script>