jQuery(function ($) {
  // Vars

  const filterTerm = $(".post-filters__term:not(.block) input");
  const wrapper = $(".archive_posts");
  const pageNumber = ".nav-links .page-numbers";
  const loader = $(".loader-wrapper");
  const searchInput = $(".filter_search input");
  const filterHeader = $(".post-filters__taxonomy .search-tax-title");
  const filterInputs = $(".post-filters__terms");
  const clearFilters = $(".clear-filters");
  const removeSearch = $(".remove-search");
  const postTerm = ".post-term";
  let searchTimeout = null;
  var controller = null;

  // Prevent search form submit on enter

  $("#search-form-filter").on("keypress", function (event) {
    const keyPressed = event.keyCode || event.which;
    if (keyPressed === 13) {
      event.preventDefault();
      return false;
    }
  });

  const filterCounter = (taxonomy) => {
    const inputs = $(`input[data-taxonomy-name=${taxonomy}]`);
    const header = $(`.search-tax-title[data-taxonomy-name=${taxonomy}]`);
    const findCounter = header.find(".terms-counter");

    const checkedInputs = inputs.filter((key, input) => {
      return $(input).prop("checked");
    });

    if (checkedInputs.length) {
      const counter = $(
        `<span class="terms-counter"> ${checkedInputs.length} </span>`
      );

      if (findCounter.length == 0) {
        header.append(counter);
      } else {
        findCounter.html(checkedInputs.length);
      }
    } else {
      findCounter.remove();
    }
  };

  // Functions

  const termTriggerFilter = (e) => {
    const currentTerm = $(e.currentTarget);
    const term = currentTerm.data("term");
    $(`.post-filters__term input[value='${term}']`).trigger("click");

    $([document.documentElement, document.body]).animate(
      {
        scrollTop: wrapper.offset().top - 235,
      },
      0
    );
  };

  const filter = (e) => {
    const currentTerm = $(e.currentTarget);
    const taxonomy = currentTerm.data("taxonomy-name");
    const termID = currentTerm.val();

    if (currentTerm.prop("checked") === true) {
      urlApi("filter-" + taxonomy, termID);
      filterCounter(taxonomy);
      ajaxCall();
    } else {
      urlApi("filter-" + taxonomy, termID, true);
      filterCounter(taxonomy);
      ajaxCall();
    }
  };

  const clearSearch = () => {
    searchInput.val("");
    searchInput.trigger("keyup");
  };

  const search = (e) => {
    e.preventDefault();

    const currentSearch = $(e.currentTarget);
    clearInterval(searchTimeout);
    searchTimeout = setTimeout(() => {
      const searchValue = currentSearch.val();
      if (searchValue != "") {
        urlApiSingle("filter-search", searchValue);
        removeSearch.show();
      } else {
        urlApiSingle("filter-search", searchValue, true);
        removeSearch.hide();
      }

      ajaxCall();
    }, 1200);
  };

  const pagination = (e) => {
    e.preventDefault();
    const currentPage = $(e.currentTarget);
    let pageNumber;
    if (currentPage.hasClass("prev")) {
      pageNumber = $(".current").text();
      pageNumber = parseInt(pageNumber) - 1;
    } else if (currentPage.hasClass("next")) {
      pageNumber = $(".current").text();
      pageNumber = parseInt(pageNumber) + 1;
    } else {
      pageNumber = currentPage.text();
    }

    urlApiSingle("pageNumber", pageNumber);

    ajaxCall().then(() => {
      $([document.documentElement, document.body]).animate(
        {
          scrollTop: wrapper.offset().top - 90,
        },
        0
      );
    });
  };

  const clearFiltersAction = (e) => {
    const current = $(e.currentTarget);
    const inputs = current.siblings();

    $.each(inputs, (index, input) => {
      if ($(input).find("input").prop("checked")) {
        $(input).find("input").trigger("click");
      }
    });
  };



  const ajaxCall = async () => {
    if (controller) {
      controller.abort();
    }

    loader.addClass("active");

    const url = new URL(window.location.href);
    const getParams = url.searchParams.toString();
    const data = new FormData();
    data.append("action", "post_filter");
    data.append("nonce", theme.nonce);
    data.append("searchParams", getParams);

    controller = new AbortController();
    let signal = controller.signal;

    const request = await fetch(theme.ajaxUrl, {
      method: "POST",
      body: data,
      signal,
    });

    if (!request.ok) {
      throw new Error(`HTTP error! status: ${request.status}`);
    }

    const posts = await request.json();

    if (posts) {
      controller = null;
      wrapper.find(".grid_item").remove();
      wrapper.find(".filter-no-items").remove();
      wrapper.prepend(posts.postsHtml);
      const paginationDOM = $(".nav-links");
      paginationDOM.replaceWith(posts.pagination);
    }

    loader.removeClass("active");
  };

  const urlApiSingle = (param, term, deleteParam = false) => {
    const url = new URL(window.location.href);
    url.searchParams.delete("pageNumber");

    if (deleteParam === true) {
      url.searchParams.delete(param);
    } else {
      url.searchParams.set(param, term);
    }

    history.pushState({}, "", url);
  };

  const urlApi = (
    param,
    term,
    deleteTerm = false,
    returnParams = false,
    urllocation = window.location.href
  ) => {
    const url = new URL(urllocation);
    const oldTerms = url.searchParams.getAll(param);
    url.searchParams.delete("pageNumber");
    if (Array.isArray(oldTerms) && oldTerms.length !== 0) {
      if (deleteTerm === false) {
        oldTerms.push(term);
        url.searchParams.set(param, oldTerms.join("-"));
      } else {
        const input = $(`#${term}`);
        const isParent = input.data("parent");
        const oldTermsSplit = oldTerms[0].split("-");
        const indexToDelete = oldTermsSplit.indexOf(term);
        oldTermsSplit.splice(indexToDelete, 1);

        if (isParent) {
          const children = input.data("children");

          if (children) {
            const childrenArray = children.split("-");
            childrenArray.forEach((val) => {
              if (oldTermsSplit.includes(val)) {
                const indexToDelete = oldTermsSplit.indexOf(val);
                oldTermsSplit.splice(indexToDelete, 1);
                const childInput = $(`#${val}`);
                childInput.prop("checked", false);
              }
            });
          }
        }

        if (oldTermsSplit.length === 0) {
          url.searchParams.delete(param);
        } else {
          url.searchParams.set(param, oldTermsSplit.join("-"));
        }
      }
    } else {
      url.searchParams.set(param, term);
    }

    if (returnParams === true) {
      return url;
    }

    history.pushState({}, "", url);
  };

  // Events

  filterTerm.on("change", filter);
  searchInput.on("keyup", search);
  removeSearch.on("click", clearSearch);
  $("body").on("click", pageNumber, pagination);
  clearFilters.on("click", clearFiltersAction);
  $("body").on("click", postTerm, termTriggerFilter);
});
