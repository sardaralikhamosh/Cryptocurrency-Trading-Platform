<!DOCTYPE html>
<html>
<head>
    <title>Cryptocurrency Chart</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .container {
            width: 80%;
            margin: 0 auto;
            padding: 20px;
        }
        .chart-container {
            position: relative;
            height: 50vh;
            width: 100%;
        }
        .controls {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        select, button {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Cryptocurrency Price Chart</h1>
        
        <div class="controls">
            <div>
                <label for="coinSelect">Cryptocurrency:</label>
                <select id="coinSelect">
                    <option value="bitcoin">Bitcoin</option>
                    <option value="ethereum">Ethereum</option>
                    <option value="ripple">XRP</option>
                    <option value="cardano">Cardano</option>
                    <option value="solana">Solana</option>
                </select>
            </div>
            
            <div>
                <label for="daysSelect">Time Period:</label>
                <select id="daysSelect">
                    <option value="1">1 Day</option>
                    <option value="7">1 Week</option>
                    <option value="30" selected>1 Month</option>
                    <option value="90">3 Months</option>
                    <option value="365">1 Year</option>
                </select>
            </div>
            
            <div>
                <label for="currencySelect">Currency:</label>
                <select id="currencySelect">
                    <option value="usd" selected>USD</option>
                    <option value="eur">EUR</option>
                    <option value="gbp">GBP</option>
                </select>
            </div>
            
            <button id="updateChart">Update Chart</button>
        </div>
        
        <div class="chart-container">
            <canvas id="cryptoChart"></canvas>
        </div>
    </div>

    <script>
        // Initialize chart
        let cryptoChart;
        
        // Function to format dates
        function formatDate(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleDateString();
        }
        
        // Function to format currency
        function formatCurrency(value, currency) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency.toUpperCase()
            }).format(value);
        }
        
        // Function to load chart data
        function loadChartData() {
            const coinId = document.getElementById('coinSelect').value;
            const days = document.getElementById('daysSelect').value;
            const currency = document.getElementById('currencySelect').value;
            
            fetch(`/crypto/data/${coinId}/${days}/${currency}`)
                .then(response => response.json())
                .then(data => {
                    const prices = data.prices;
                    
                    // Format data for Chart.js
                    const labels = prices.map(price => formatDate(price[0]));
                    const priceData = prices.map(price => price[1]);
                    
                    // Destroy existing chart if it exists
                    if (cryptoChart) {
                        cryptoChart.destroy();
                    }
                    
                    // Create new chart
                    const ctx = document.getElementById('cryptoChart').getContext('2d');
                    cryptoChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: `${coinId.charAt(0).toUpperCase() + coinId.slice(1)} Price (${currency.toUpperCase()})`,
                                data: priceData,
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1,
                                pointRadius: 0, // Hide points for cleaner look
                                tension: 0.1 // Slight curve for lines
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    ticks: {
                                        maxTicksLimit: 10 // Limit number of x-axis labels
                                    }
                                },
                                y: {
                                    ticks: {
                                        callback: function(value) {
                                            return formatCurrency(value, currency);
                                        }
                                    }
                                }
                            },
                            interaction: {
                                intersect: false,
                                mode: 'index'
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return formatCurrency(context.parsed.y, currency);
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error loading chart data:', error));
        }
        
        // Load chart when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadChartData();
            
            // Add event listener for update button
            document.getElementById('updateChart').addEventListener('click', loadChartData);
        });
    </script>
</body>
</html>