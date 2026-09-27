/**
 * Just the parts of ECharts the sales report draws: a line, a bar, a grid and
 * a tooltip. The full `echarts` entry registers every chart type and component
 * it ships — most of a megabyte — for two series.
 *
 * Imported dynamically, so a report whose chart has nothing to draw never
 * downloads it at all.
 */
import * as echarts from 'echarts/core';
import { LineChart, BarChart } from 'echarts/charts';
import { GridComponent, TooltipComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([LineChart, BarChart, GridComponent, TooltipComponent, CanvasRenderer]);

export default echarts;
