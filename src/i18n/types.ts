export type FooterPaths = {
  cancel: string;
  terms: string;
  privacy: string;
  reserve: string;
  contact: string;
};

export type Dict = {
  header: { title: string; tagline: string };
  nav: {
    home: string;
    find: string;
    plan: string;
    know: string;
    about: string;
    language: string;
  };
  ui: {
    changeLanguage: string;
    changeCurrency: string;
  };
  home: {
    checkin: string;
    checkout: string;
    guests: string;
    search: string;
  };
  gallery: {
    aria: { prev: string; next: string; close: string; gallery: string };
    categories: {
      Interiores: string;
      Exteriores: string;
      Parque: string;
      Pileta: string;
    };
    badge: { viewMoreCabin: string; backToCategory: string };
  };
  footer: {
    contactTitle: string;
    phone: string;
    email: string;
    address: string;
    socialTitle: string;
    usefulTitle: string;
    cancelPolicy: string;
    terms: string;
    privacy: string;
    ctaReserve: string;
    ctaContact: string;
    rights: string;
    paths?: FooterPaths;
  };
};
