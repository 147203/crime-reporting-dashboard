import 'bootstrap';
import { initTheme } from './modules/theme';
import { initCharts } from './modules/charts';
import { initWardSelects } from './modules/ward-select';
import { initConfirmations } from './modules/confirm';
import { initListFilters } from './modules/list-filter';
import { initPasswordToggles } from './modules/password-toggle';
import { initScrollRegions } from './modules/scroll-regions';
import { initVictimForm } from './modules/victim-form';

initTheme();
initWardSelects();
initConfirmations();
initListFilters();
initPasswordToggles();
initScrollRegions();
initVictimForm();
initCharts();
