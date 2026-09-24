import 'bootstrap';
import { initTheme } from './modules/theme';
import { initCharts } from './modules/charts';
import { initWardSelects } from './modules/ward-select';
import { initConfirmations } from './modules/confirm';
import { initListFilters } from './modules/list-filter';

initTheme();
initWardSelects();
initConfirmations();
initListFilters();
initCharts();
