define(["jquery", "core/ajax"], function ($, Ajax) {
  return {
    init: function (
      baseUrl,
      token,
      externalToken,
      activityId,
      responseId,
      showRetryBtn,
      idQt,
      seedQt
    ) {
      $(document).ready(function () {
        // Expose showHideSolution to global window object
        window.show_hide_solution = showHideSolution;

        // Initialise MathJax typesetting
        var nodes = Y.all(".latex-math");
        Y.fire(M.core.event.FILTER_CONTENT_UPDATED, { nodes: nodes });

        showRetryBtn = parseInt(showRetryBtn);

        $(document).on("click", ".check-answer-button", function (e) {
          e.preventDefault();
          submitResponse();
        });

        $(document).on("click", ".sv-button--goto-question", function (e) {
          ResetUrlParameters();
        });

        function submitResponse() {
          // Get all Siyavula inputs that have not been marked "readonly"
          var formData = $(".response-query input, .response-query select")
            .not('[name*="|readonly"]')
            .serialize();

          var promises = Ajax.call([
            {
              methodname: "filter_siyavula_submit_answers_siyavula",
              args: {
                baseurl: baseUrl,
                token: token,
                external_token: externalToken,
                activityid: activityId,
                responseid: responseId,
                data: formData,
              },
            },
          ]);

          promises[0]
            .done(function (response) {
              var responseData = JSON.parse(response.response);
              updateHtml(responseData.response.question_html);
            })
            .fail(function (e) {
              console.log(e);
            });
        }

        function updateHtml(html) {
          // Replace question HTML with marked HTML returned from the API
          $(".question-content").html(html);

          if (!showRetryBtn) {
            $(".sv-button--retry-question").style.display = "none";
          } else {
            setRetryUrlParameters();
          }

          // Typeset new HTML content
          MathJax.Hub.Queue(["Typeset", MathJax.Hub]);
        }

        function setRetryUrlParameters() {
          var url = new URL(location.href);
          var search_params = url.searchParams;

          search_params.set("aid", activityId);
          search_params.set("rid", responseId);

          url.search = search_params.toString();

          var new_url = url.toString();

          const retry = document.querySelector('a[name="retry"]');
          if (retry) {
            retry.setAttribute("href", new_url);
          }
        }

        function ResetUrlParameters() {
          var url = new URL(location.href);
          var search_params = url.searchParams;

          search_params.delete("aid");
          search_params.delete("rid");

          url.search = search_params.toString();

          var new_url = url.toString();

          const nextPage = document.querySelector('a[name="nextPage"]');
          if (nextPage) {
            nextPage.setAttribute("href", new_url);
          }
        }

        function showHideSolution(button) {
          const $button = jQuery(button);

          // Toggle solution visibility
          $button.parent().next().slideToggle("slow");

          // Toggle button text
          const previousValue = $button.attr("value");
          const nextValue = $button.attr("data-alt-value");
          $button
            .attr("value", nextValue)
            .attr("data-alt-value", previousValue);
        }
      });
    },
  };
});
