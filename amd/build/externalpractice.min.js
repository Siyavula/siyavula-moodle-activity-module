define(["jquery", "core/ajax"], function ($, Ajax) {
  return {
    init: function (
      baseurl,
      token,
      external_token,
      activityid,
      responseid,
      idsq,
      currenturl,
      next_id,
      $siyavula_activity_id,
      show_retry_btn
    ) {
      $(document).ready(function () {
        // Initialise MathJax typesetting
        var nodes = Y.all(".latex-math");
        Y.fire(M.core.event.FILTER_CONTENT_UPDATED, { nodes: nodes });

        show_retry_btn = parseInt(show_retry_btn);
        $(".question-content").on("click", function (e) {
          const response = e.currentTarget.dataset.response;
          const targetid = e.currentTarget.id;
          if (
            e.target.className ===
            "sv-button sv-button--primary check-answer-button"
          ) {
            e.preventDefault();
            var formData = $(
              `div#${targetid} form[name="questions"]`
            ).serialize();
            var submitresponse = Ajax.call([
              {
                methodname: "filter_siyavula_submit_answers_siyavula",
                args: {
                  baseurl: baseurl,
                  token: token,
                  external_token: external_token,
                  activityid: targetid,
                  responseid: response,
                  data: formData,
                },
              },
            ]);
            submitresponse[0]
              .done(function (response) {
                var dataresponse = JSON.parse(response.response);
                var html = dataresponse.response.question_html;
                let timest = Math.floor(Date.now() / 1000);
                html = html.replaceAll(
                  "sv-button toggle-solution",
                  `sv-button toggle-solution btnsolution-${targetid}-${timest}`
                );
                $(`#${targetid}.question-content`).html(html);
                $(`div#${targetid} .toggle-solution-checkbox`).css(
                  "visibility",
                  "hidden"
                );

                const retry = document.querySelector('a[name="retry"]');
                if (retry) {
                  retry.setAttribute(
                    "href",
                    location.href +
                      (location.href.includes("?") ? "&" : "?") +
                      "aid=" +
                      activityid +
                      "&" +
                      "rid=" +
                      responseid
                  );
                }

                const nextqt = document.querySelector('a[name="nextPage"]');
                if (nextqt) {
                  nextqt.setAttribute(
                    "href",
                    location.href +
                      (location.href.includes("?") ? "&" : "?") +
                      "sectionid=" +
                      idqt +
                      "&" +
                      "seed=" +
                      seedqt
                  );
                }

                const theId = targetid;
                const escapeID = CSS.escape(theId);

                const labelsSolution = document.querySelectorAll(
                  `#${escapeID}.question-content #show-hide-solution`
                );

                labelsSolution.forEach((labelSolution, key) => {
                  labelSolution.innerHTML = "";

                  const newShowSpan = document.createElement("input");
                  newShowSpan.classList.add("sv-button");
                  newShowSpan.value = "Show the full solution";
                  newShowSpan.type = "button";
                  newShowSpan.id = `show${key}`;

                  const newHideSpan = document.createElement("input");
                  newHideSpan.value = "Hide the full solution";
                  newHideSpan.classList.add("sv-button");
                  newHideSpan.type = "button";
                  newHideSpan.id = `hide${key}`;

                  var is_correct = true;
                  const rsElement = labelSolution.nextSibling; // Response information
                  const identificador = `${rsElement.id}-${key}`;
                  rsElement.classList.add(identificador);
                  console.log(rsElement);
                  if (rsElement.id == "correct-solution") {
                    is_correct = true;
                  } else {
                    is_correct = false;
                  }

                  if (is_correct == false) {
                    //$(`div#${targetid} span:contains('Show the full solution')`).css("display", "none");
                    newShowSpan.style.display = "none";
                  } else {
                    //$(`div#${targetid} span:contains('Hide the full solution')`).css("display", "none");
                    newHideSpan.style.display = "none";
                  }
                  labelSolution.append(newShowSpan);
                  labelSolution.append(newHideSpan);

                  const spanShow = labelSolution.querySelector(`#show${key}`);
                  const spanHide = labelSolution.querySelector(`#hide${key}`);
                  const functionClickSolution = (btnE) => {
                    const currentSpan = btnE.target;
                    if (currentSpan.value.includes("Show")) {
                      spanShow.style.display = "none";
                      spanHide.style.display = "initial";
                    } else {
                      spanShow.style.display = "initial";
                      spanHide.style.display = "none";
                    }

                    $(`.${identificador}`).slideToggle();
                  };
                  spanShow.addEventListener("click", functionClickSolution);
                  spanHide.addEventListener("click", functionClickSolution);
                });

                MathJax.Hub.Queue(["Typeset", MathJax.Hub]);
              })
              .fail(function (ex) {
                console.log(ex);
              });
          }
        });

        $("p:contains('sy-')").css("display", "none");
        if ($("#qt")[0]) {
          $("#qt")[0].nextSibling.remove();
        }
      });
    },
  };
});
