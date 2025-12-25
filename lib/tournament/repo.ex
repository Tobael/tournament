defmodule Tournament.Repo do
  use Ecto.Repo,
    otp_app: :tournament,
    adapter: Ecto.Adapters.Postgres
end
