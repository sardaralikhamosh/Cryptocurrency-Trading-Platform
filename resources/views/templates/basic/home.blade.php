@extends($activeTemplate . 'layouts.frontend')
@section('content')
    @php
        $banner   = getContent('banner.content', true);
        $elements = getContent('banner.element');
    @endphp

    <section class="banner-section">
        <div class="banner-section__shape light-mood">
            <img src="{{ asset($activeTemplateTrue . 'images/shapes/banner_1.png') }}">
        </div>
        <div class="banner-section__shape dark-mood">
            <img src="{{ asset($activeTemplateTrue . 'images/shapes/banner_1_dark.png') }}">
        </div>
        <div class="banner-section__shape-one light-mood">
            <img src="{{ asset($activeTemplateTrue . 'images/shapes/bg.png') }}">
        </div>
        <div class="banner-section__shape-one dark-mood">
            <img src="{{ asset($activeTemplateTrue . 'images/shapes/bg_dark.png') }}">
        </div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="banner-content ">
                        <h1 class="banner-content__title">
                            @php echo highLightedString(@$banner->data_values->heading); @endphp
                        </h1>
                        <p class="banner-content__desc">
                            @php echo highLightedString(@$banner->data_values->subheading,'fw-bold'); @endphp
                        </p>
                        <div class="banner-content__button d-flex align-items-center gap-3">
                            <a href="{{ @$banner->data_values->button_link }}" class="btn btn--base">
                                {{ __(@$banner->data_values->button_text) }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="banner-right">
                        <div class="banner-right__thumb">
                            <img src="{{ getImage('assets/images/frontend/banner/' . @$banner->data_values->image_one, '500x360') }}">
                            <div class="banner-right__thumb-shape">
                                <img src="{{ getImage('assets/images/frontend/banner/' . @$banner->data_values->image_two, '155x155') }}">
                            </div>
                        </div>
                        <div class="banner-right__shape">
                            <img src="{{ getImage('assets/images/frontend/banner/' . @$banner->data_values->image_three, '450x285') }}">
                        </div>
                        <div class="banner-right__bg">
                            <div class="banner-right__shape-bg-one bg"></div>
                            <div class="banner-right__shape-bg-two bg"></div>
                            <div class="banner-right__shape-bg-three bg"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-5">
                <div class="col-lg-12">
                    <div class="crypto-chart-wrapper">
                        <h4 class="mb-3">@lang('Cryptocurrency Market')</h4>
                        
                        <div class="controls mb-3">
                            <div class="d-flex flex-wrap gap-3 align-items-center">
                                <div>
                                    <label for="coinSelect">@lang('Cryptocurrency'):</label>
                                    <select id="coinSelect" class="form-select">
                                        <option value="bitcoin">Bitcoin</option>
                                        <option value="ethereum">Ethereum</option>
                                        <option value="ripple">XRP</option>
                                        <option value="cardano">Cardano</option>
                                        <option value="solana">Solana</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="daysSelect">@lang('Time Period'):</label>
                                    <select id="daysSelect" class="form-select">
                                        <option value="1">1 @lang('Day')</option>
                                        <option value="7">1 @lang('Week')</option>
                                        <option value="30" selected>1 @lang('Month')</option>
                                        <option value="90">3 @lang('Months')</option>
                                        <option value="365">1 @lang('Year')</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="currencySelect">@lang('Currency'):</label>
                                    <select id="currencySelect" class="form-select">
                                        <option value="usd" selected>USD</option>
                                        <option value="eur">EUR</option>
                                        <option value="gbp">GBP</option>
                                    </select>
                                </div>
                                
                                <button id="updateChart" class="btn btn--base">@lang('Update Chart')</button>
                            </div>
                        </div>
                        
                        <div class="chart-container" style="position: relative; height: 50vh; width: 100%;">
                            <canvas id="cryptoChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @include($activeTemplate . 'sections.blog')

    @if ($sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @include($activeTemplate . 'sections.' . $sec)
        @endforeach
    @endif
@endsection

@push('script-lib')
    <script src="{{ asset($activeTemplateTrue . 'js/swiper.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush

@push('style-lib')
    <link rel="stylesheet" href="{{ asset($activeTemplateTrue . 'css/swiper.css') }}">
@endpush

@push('script')
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
@endpush

@php app()->offsetSet('swiper_assets',true) @endphp