import { length } from "@/helpers/object";
import { reactive } from "vue";

/**
 * @since 5.0.0
 */
export default (panel) => {
	return reactive({
		/**
		 * API endpoint to handle content changes
		 */
		get api() {
			return panel.view.props.api;
		},

		/**
		 * Returns all fields and their values that
		 * have been changed but not yet saved
		 *
		 * @returns {Object}
		 */
		get changes() {
			const changes = {};

			for (const field in panel.view.props.content) {
				const changed = JSON.stringify(panel.view.props.content[field]);
				const original = JSON.stringify(panel.view.props.originals[field]);

				if (changed !== original) {
					changes[field] = panel.view.props.content[field];
				}
			}

			return changes;
		},

		/**
		 * Removes all unpublished changes
		 */
		async discard() {
			if (this.isProcessing === true) {
				return;
			}

			this.isProcessing = true;

			try {
				await panel.post(this.api + "/changes/discard");
				panel.view.props.content = panel.view.props.originals;
				panel.view.reload();
			} finally {
				this.isProcessing = false;
			}
		},

		/**
		 * Whether there are any changes
		 *
		 * @returns {Boolean}
		 */
		get hasChanges() {
			return length(this.changes) > 0;
		},

		/**
		 * Whether content is currently being discarded, saved or published
		 * @var {Boolean}
		 */
		isProcessing: false,

		/**
		 * The last published state
		 *
		 * @returns {Object}
		 */
		get originals() {
			return panel.view.props.originals;
		},

		/**
		 * Publishes any changes
		 */
		async publish() {
			if (this.isProcessing === true) {
				return;
			}

			this.isProcessing = true;

			// Send updated values to API
			try {
				await panel.post(
					this.api + "/changes/publish",
					panel.view.props.content
				);

				panel.view.props.originals = panel.view.props.content;

				await panel.view.refresh();
			} finally {
				this.isProcessing = false;
			}

			panel.events.emit("model.update");
			panel.notification.success();
		},

		/**
		 * Saves any changes
		 */
		async save() {
			this.isProcessing = true;

			// ensure to abort unfinished previous save request
			// to avoid race conditions with older content
			this.saveAbortController?.abort();
			this.saveAbortController = new AbortController();

			try {
				await panel.post(this.api + "/changes/save", panel.view.props.content, {
					signal: this.saveAbortController.signal
				});

				// update the last modification timestamp
				panel.view.props.lock.modified = new Date();

				this.isProcessing = false;
			} catch (error) {
				// silent aborted requests, but throw all other errors
				if (error.name !== "AbortError") {
					this.isProcessing = false;
					throw error;
				}
			}
		},

		/**
		 * @internal
		 * @var {AbortController}
		 */
		saveAbortController: null,

		/**
		 * Updates the values of fields
		 *
		 * @param {Object} values
		 */
		update(values) {
			if (length(values) === 0) {
				return;
			}

			panel.view.props.content = {
				...panel.view.props.originals,
				...values
			};
		},

		/**
		 * Returns all fields and values incl. changes
		 *
		 * @returns {Object}
		 */
		get values() {
			return panel.view.props.content;
		}
	});
};
