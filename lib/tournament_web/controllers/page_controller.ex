defmodule TournamentWeb.PageController do
  use TournamentWeb, :controller

  def home(conn, _params) do
    render(conn, :home)
  end
end
