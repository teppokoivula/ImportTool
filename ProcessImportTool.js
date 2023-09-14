document.addEventListener("DOMContentLoaded", function() {
	const processImportToolSubmit = document.getElementById('process-import-tool-submit');
	processImportToolSubmit.addEventListener('click', () => {
		processImportToolSubmit.disabled = true;
		processImportToolSubmit.classList.add('ui-state-disabled');
		const processImportToolSubmitIcon = document.createElement('i');
		processImportToolSubmitIcon.setAttribute('class', 'uk-margin-small-left fa fa-circle-o-notch fa-spin');
		processImportToolSubmit.appendChild(processImportToolSubmitIcon);
	});
});
