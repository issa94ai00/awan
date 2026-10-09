/**
 * Just the parts of ECharts the admin reports draw: lines, bars, a grid, a
 * tooltip and a legend. The full `echarts` entry registers every chart type
 * and component it ships — most of a megabyte — for two series.
 *
 * Imported dynamically, so a report whose chart has nothing to draw never
 * downloads it at all.
 */
import * as echarts from 'echarts/core';
import { LineChart, BarChart } from 'echarts/charts';
import { GridComponent, TooltipComponent, LegendComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([LineChart, BarChart, GridComponent, TooltipComponent, LegendComponent, CanvasRenderer]);

export default echarts;
