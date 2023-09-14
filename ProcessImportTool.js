document.addEventListener("DOMContentLoaded", function() {
	const processImportToolSubmit = document.getElementById('process-import-tool-submit');
	processImportToolSubmit.addEventListener('click', () => {
		processImportToolSubmit.disabled = true;
		processImportToolSubmit.classList.add('ui-state-disabled');
	});
});
