document.addEventListener("DOMContentLoaded", function() {
	const processImportToolForm = document.getElementById('process-import-tool-form');
	const processImportToolSubmit = document.getElementById('process-import-tool-submit');
	processImportToolForm.addEventListener('submit', () => {
		processImportToolSubmit.setAttribute('style', 'pointer-events: none');
		processImportToolSubmit.classList.add('ui-state-disabled');
		const processImportToolSubmitIcon = document.createElement('i');
		processImportToolSubmitIcon.setAttribute('class', 'uk-margin-small-left fa fa-circle-o-notch fa-spin');
		processImportToolSubmit.appendChild(processImportToolSubmitIcon);
	});
});
