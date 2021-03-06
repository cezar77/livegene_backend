<template>
  <div>
    <h2 class="bg-info text-white text-center p-2">Project Timelines</h2>
    <b-row align-h="center" align-v="center" class="content" v-show="!loaded && !error">
      <b-spinner label="Loading..." class="mt-5"></b-spinner>
    </b-row>
    <b-row align-h="center" align-v="center" class="content" v-show="!loaded && error">
      <b-alert variant="danger" show>
        Error message: <strong>{{ errorStatusText }}</strong>
      </b-alert>
    </b-row>
    <b-row align-h="center" class="text-center pb-5 content" v-show="loaded">
      <svg id="viewport" :width="viewport.width" :height="viewport.height">
        <rect id="zoom" :x="margin.left" :y="margin.top" :width="chart.width" :height="chart.height"></rect>
        <defs>
          <linearGradient id="legendGradient" x1="0" x2="1">
            <stop offset="0" :style="{ 'stop-color': colorScale(value.min), 'stop-opacity': 0.8 }"></stop>
            <stop offset="1" :style="{ 'stop-color': colorScale(value.max), 'stop-opacity': 0.8 }"></stop>
          </linearGradient>
          <clipPath id="chart">
            <rect :x="margin.left" :y="margin.top" :width="chart.width" :height="chart.height"></rect>
          </clipPath>
        </defs>
        <x-axis :axis="xAxis" :margin="margin" :chart="chart" class="x-axis"></x-axis>
        <y-axis :axis="yAxis" :margin="margin" :chart="chart" :bar-height="barHeight" :spacing="spacing" :data="data" class="y-axis"></y-axis>
        <line class="top-border" :x1="margin.left" :y1="margin.top" :x2="margin.left + chart.width" :y2="margin.top"></line>
        <line class="right-border" :x1="margin.left + chart.width" :y1="margin.top" :x2="margin.left + chart.width" :y2="margin.top + chart.height"></line>
        <g class="view" clip-path="url(#chart)">
          <g></g>
        </g>
        <g class="legend">
          <text :transform="`translate(${[viewport.width/2, spacing * 2]})`">Total Project Value</text>
          <rect class="gradient-bar" :x="(viewport.width - legendBox.width) / 2" :y="spacing * 3"
                :width="legendBox.width" :height="legendBox.height"></rect>
          <text :x="(viewport.width - legendBox.width) / 2 - spacing"
                :y="spacing * 2 + legendBox.height" class="value-min">
            {{ `\u2264 ${moneyFormat(value.min)}` }}
          </text>
          <text :x="(viewport.width - legendBox.width) / 2 + legendBox.width + spacing"
                :y="spacing * 2 + legendBox.height" class="value-max">
            {{ `\u2265 ${moneyFormat(value.max)}` }}
          </text>
          <g class="value-indicator" :transform="`translate(${[(viewport.width - legendBox.width)/2, spacing * 3 + legendBox.height]})`">
            <text class="caret-up">{{ '\uf0d8' }}</text>
            <text class="project-value"></text>
          </g>
        </g>
        <g class="today" :transform="`translate(${[today, margin.top]})`">
          <line :y2="chart.height"></line>
          <text :style="{ fontSize: barHeight + spacing }" @click="toggleActiveProjects">{{ '\uf274' }}</text>
        </g>
      </svg>
      <b-button variant="danger" size="sm" id="reset" @click="resetChart">
        Reset
      </b-button>
      <div class="infobox"></div>
    </b-row>
  </div>
</template>

<script>
  import * as d3 from 'd3';
  import { mapState } from 'vuex';
  import XAxis from './XAxis';
  import YAxis from './YAxis';

  export default {
    name: 'ProjectTimelines',
    components: { XAxis, YAxis },
    data () {
      return {
        barHeight: 12,
        spacing: 6,
        margin: {
          top: 80,
          left: 100,
          right: 20,
          bottom: 50
        },
        tickBleed: 10,
        labelPadding: 5,
        value: {
          min: 100000,
          max: 10000000
        },
        moneyFormat: d3.format('$,.0f'),
        dateFormat: d3.timeFormat('%b %d, %Y'),
        today: 0,
        todayOn: false
      }
    },
    computed: {
      /**
       * Get the data from Vuex Store
       */
      ...mapState({
        projects: state => state.projects,
        data: state => state.projectsGroupedByTeam,
        loaded: state => state.loaded,
        error: state => state.error,
        errorStatusText: state => state.errorStatusText
      }),
      /**
       * Get the base width used to calculate the viewport and chart dimensions
       *
       * @returns {number}
       */
      baseWidth: function () {
        return window.innerWidth >= 992 ? window.innerWidth - 2 * Math.round(window.innerWidth/10) : window.innerWidth;
      },
      /**
       * Calculate the dimensions of the viewport
       *
       * @returns {{width: (default.computed.baseWidth|(function(): number)), height: *}}
       */
      viewport: function () {
        return {
          width: this.baseWidth,
          height: this.chart.height + this.margin.top + this.margin.bottom
        }
      },
      /**
       * Calculate the dimensions of the chart
       *
       * @returns {{width: number, height: number}}
       */
      chart: function () {
        return {
          width: this.baseWidth - (this.margin.left + this.margin.right),
          height: this.projects.length * this.barHeight + this.data.size * this.spacing
        }
      },
      /**
       * Get the begin of the x axis
       * It is the calendar year of the earliest start date
       *
       * @returns {*}
       */
      xMin: function () {
        return d3.min(
          this.projects,
          d => new Date(
            String(d3.isoParse(d.startDate).getFullYear())
          )
        );
      },
      /**
       * Get the end of the x axis
       * It is the calendar year of the latest end date
       *
       * @returns {*}
       */
      xMax: function () {
        return d3.max(
          this.projects,
          d => new Date(
            String(d3.isoParse(d.endDate).getFullYear() + 1)
          )
        );
      },
      /**
       * Create time scale for the x axis
       *
       * @returns {*}
       */
      xScale: function () {
        return d3.scaleTime()
          .range([this.margin.left, this.margin.left + this.chart.width])
          .domain([this.xMin, this.xMax]);
      },
      /**
       * Create ordinal scale for the y axis
       * @returns {*}
       */
      yScale: function () {
        const offsets = [0];
        this.data.forEach(function (d) {
          this.push(d.length);
        }, offsets);

        return d3.scaleOrdinal()
          .range(offsets.map(
            (d, i, a) => a.slice(0, (i + 1))
              .reduce((acc, cur) => acc + cur, 0) * this.barHeight + i * this.spacing
          ))
          .domain([...this.data.keys()])
          ;
      },
      /**
       * Create bottom x axis
       *
       * @returns {*}
       */
      xAxis: function () {
        return d3.axisBottom(this.xScale)
          .ticks(10)
          .tickSize(this.chart.height + this.tickBleed)
          .tickPadding(this.labelPadding)
          .tickSizeOuter(0);
      },
      /**
       * Create left y axis
       *
       * @returns {*}
       */
      yAxis: function () {
        return d3.axisLeft(this.yScale)
          .tickSizeInner(this.chart.width)
          .tickSizeOuter(0)
        ;
      },
      /**
       * Create sequential scale for the project values
       *
       * @returns {*}
       */
      colorScale: function () {
        return d3
          .scaleSequential(d3.interpolateReds)
          .domain([this.value.min, this.value.max])
        ;
      },
      /**
       * Calculate the dimensions of the legend box
       *
       * @returns {{width: number, height: number}}
       */
      legendBox: function () {
        return {
          width: this.baseWidth >= 794 ? 500 : 300,
          height: 20
        }
      },
      /**
       * Create linear scale for the legend
       *
       * @returns {*|[*, *]|null|[*, *]}
       */
      legendScale: function () {
        return d3.scaleLinear()
          .domain([this.value.min, this.value.max])
          .range([0, this.legendBox.width])
          .clamp(true)
        ;
      }
    },
    methods: {
      renderChart: function () {
        // Create chart area
        const svg = d3.select('#viewport');
        const view = svg.select('g.view > g');

        // Create groups for all teams
        const teams = view.selectAll('g.team')
          .data([...this.data])
          .join('g')
          .attr('class', 'team')
          .attr(
            'transform',
            d => `translate(${[0, this.margin.top + this.yScale(d[0])]})`
          )
        ;

        const timelines = teams.selectAll('g.timeline')
          .data(d => d[1])
          .join('g')
          .attr('class', 'timeline')
          .attr(
            'transform',
            (d, i) => `translate(0, ${i * this.barHeight + this.spacing/2})`
          )
          .on('mouseenter', this.showProjectDetails)
          .on('mouseleave', this.hideProjectDetails)
        ;

        timelines.append('rect')
          .attr('class', 'project')
          .attr('x', d => this.xScale(d3.isoParse(d.startDate)))
          .style('fill', d => this.colorScale(d.totalProjectValue))
          .style('opacity', 0.8)
          .style('stroke', 'blueviolet')
          .style('stroke-opacity', 0.5)
          .attr('height', this.barHeight)
          .transition('timeline')
          .ease(d3.easeLinear)
          .delay(d => (this.xScale(d3.isoParse(d.startDate)) - this.xScale(this.xMin)) * 5)
          .duration(d => (this.xScale(d3.isoParse(d.endDate)) - this.xScale(d3.isoParse(d.startDate))) * 5)
          .attr(
            'width',
            d => this.xScale(d3.isoParse(d.endDate)) - this.xScale(d3.isoParse(d.startDate))
          )
        ;

        // project labels
        timelines.append('text')
          .attr('class', 'project-label')
          .attr('x', d => this.labelPadding + this.xScale(d3.isoParse(d.startDate)))
          .attr('y', this.barHeight/2 + 1)
          .style('font-size', this.barHeight - 1)
          .style('alignment-baseline', 'middle')
          .style('font-family', '"Open Sans", sans-serif')
          .style('font-weight', 800)
          .style('fill', 'azure')
          .style('mix-blend-mode', 'difference')
          .transition('timeline')
          .ease(d3.easeLinear)
          .delay(d => (this.xScale(d3.isoParse(d.startDate)) - this.xScale(this.xMin)) * 5 + 200)
          .text(d => d.shortName)
        ;

        // call zoom
        const zoom = this.createZoom();
        svg
          .select('#zoom')
          .call(zoom)
        ;

        // adjust reset button
        const resetButton = document.querySelector('#reset');
        resetButton.style.marginLeft = `${-resetButton.offsetWidth}px`;
      },
      createZoom: function () {
        return d3.zoom()
          .scaleExtent([1, 10])
          .extent([[0, 0], [this.chart.width, this.chart.height]])
          .on('zoom', this.updateChart)
        ;
      },
      showProjectDetails: function (d, i, n) {
        const svg = d3.select('#viewport');

        svg.select('text.caret-up')
          .attr('x', this.legendScale(d.totalProjectValue))
          .style('opacity', 1)
        ;
        svg.select('text.project-value')
          .attr('x', this.legendScale(d.totalProjectValue))
          .attr('y', this.spacing * 3)
          .style('text-anchor', 'middle')
          .style('font-family', '"Yanone Kaffeesatz", sans-serif')
          .style('opacity', 1)
          .text(this.moneyFormat(d.totalProjectValue))
        ;

        d3.select(n[i])
          .select('rect.project')
          .style('fill', 'yellow')
          .style('stroke', 'red')
        ;

        d3.select('div.infobox')
          .style('display', 'block')
          .style('top', `${d3.event.pageY}px`)
          .style('left', `${d3.event.pageX}px`)
          .html(
            `<h6>${d.fullName}</h6>
             <span>ILRI code: <strong>${d.ilriCode}</strong></span>
             <br>
             <span>Start: <strong>${this.dateFormat(d3.isoParse(d.startDate))}</strong></span>
             <br>
             <span>End: <strong>${this.dateFormat(d3.isoParse(d.endDate))}</strong></span>`
          )
        ;
      },
      hideProjectDetails: function (d, i, n) {
        const svg = d3.select('#viewport');
        svg.select('text.caret-up')
          .style('opacity', 0);
        svg.select('text.project-value')
          .style('opacity', 0);
        d3.select(n[i]).select('rect')
          .style('fill', d => this.colorScale(d.totalProjectValue))
          .style('stroke', 'blueviolet')
        ;
        d3.select('div.infobox').style('display', 'none');
      },
      getTodayPosition: function () {
        let todayPosition = this.xScale(d3.isoParse(new Date()));
        this.today =  Boolean(todayPosition) ? todayPosition : 0;
      },
      toggleActiveProjects: function () {
        const svg = d3.select('#viewport');
        this.todayOn = !this.todayOn;
        if (this.todayOn) {
          svg.select('g.today')
            .style('stroke-opacity', 1)
          ;
          svg.selectAll('g.timeline')
            .style(
              'visibility',
              d => d.isActive ? 'visible' : 'hidden'
            );
        } else {
          svg.select('g.today')
            .style('stroke-opacity', 0);
          svg.selectAll('g.timeline')
            .style('visibility', 'visible');
        }
      },
      updateChart: function () {
        const newX = d3.event.transform.rescaleX(this.xScale);
        this.xAxis.scale(newX);
        d3.select('.x-axis').call(this.xAxis);
        d3.select('.x-axis')
          .selectAll('line')
          .attr('stroke', 'lightgray')
          .attr('opacity', '0.8')
          .attr('shape-rendering', 'crispEdges')
        ;
        const today = newX(d3.isoParse(new Date()));

        d3.selectAll('rect.project')
          .attr('x', d => newX(d3.isoParse(d.startDate)))
          .attr('width', d => newX(d3.isoParse(d.endDate)) - newX(d3.isoParse(d.startDate)))
        ;
        d3.selectAll('text.project-label')
          .attr(
            'x',
            (d, i) => {
              let newStart = newX(d3.isoParse(d.startDate));
              let newEnd = newX(d3.isoParse(d.endDate));
              if (newStart < this.margin.left && newEnd > this.margin.left) {
                return this.labelPadding + this.margin.left;
              } else {
                return this.labelPadding + newStart;
              }
            }
          )
        ;

        const todayGroup = d3.select('.today');
        todayGroup.attr(
          'transform',
          `translate(${[today, this.margin.top]})`
        );
        if (today < this.margin.left || today > this.viewport.width - this.margin.right) {
          todayGroup.attr('opacity', 0)
        } else {
          todayGroup.attr('opacity', 1)
        }

        d3.select('#reset')
          .transition('displayResetButton')
          .duration(1000)
          .style('opacity', 1)
        ;
      },
      resetChart: function () {
        const zoom = this.createZoom();
        d3.select('#zoom')
          .transition('resetting')
          .duration(1000)
          .call(zoom.transform, d3.zoomIdentity)
        ;
      }
    },
    mounted () {
      if (this.loaded) {
        this.getTodayPosition();
        this.renderChart();
      }
    },
    watch: {
      loaded (val) {
        if (val) {
          this.getTodayPosition();
          this.renderChart();
        }
      }
    }
  }
</script>

<style scoped>
  .content {
    margin: 0;
  }

  svg#viewport {
    overflow: visible;
    border: thin solid lightgray;
    background-color: azure;
  }

  #zoom {
    fill: none;
    pointer-events: all;
  }

  .top-border, .right-border {
    stroke: blue;
    shape-rendering: crispEdges;
  }

  g.infobox {
    opacity: 0;
    pointer-events: none;
  }

  g.infobox rect {
    fill: #eee;
    fill-opacity: 0.7;
    stroke: gray;
    stroke-opacity: 0.5;
    stroke-width: 2;
  }

  g.infobox text {
    text-anchor: start;
    alignment-baseline: middle;
  }

  .project-name {
    font-weight: 700;
  }

  .project-dates {
    font-weight: 400;
    font-size: 0.8em;
  }

  .legend text:first-of-type {
    alignment-baseline: middle;
    text-anchor: middle;
  }

  .gradient-bar {
    fill: url(#legendGradient);
    stroke: blueviolet;
    stroke-opacity: 0.5;
  }

  .legend .value-min {
    text-anchor: end;
  }

  .legend .value-min, .legend .value-max {
    alignment-baseline: top;
  }

  .caret-up {
    font-weight: 900;
    font-family: 'FontAwesome';
    text-anchor: middle;
    alignment-baseline: middle;
    opacity: 0;
  }

  .today {
    stroke-opacity: 0;
  }

  .today line {
    stroke: darkred;
    stroke-width: 2;
  }

  .today text {
    font-family: 'FontAwesome';
    font-weight: 900;
    text-anchor: middle;
    alignment-baseline: ideographic;
    cursor: pointer;
    fill: darkred;
  }

  #reset {
    position: absolute;
    opacity: 0;
  }

  div.infobox {
    position: absolute;
    padding: 0.2em;
    pointer-events: none;
    border: thin solid black;
    width: 200px;
    min-height: 100px;
    background-color: #fcfcfc;
    box-sizing: border-box;
    font-size: 0.8em;
    font-family: '"Open Sans Condensed"', sans-serif;
    font-weight: 500;
    opacity: 0.8;
    display: none;
  }
</style>
